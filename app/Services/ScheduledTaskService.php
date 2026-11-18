<?php
namespace App\Services;
use App\Models\{ScheduledTaskRun,User,AuditLog};
use App\Support\AuditRedactor;
use Illuminate\Console\Scheduling\{Schedule,Event};
use Illuminate\Support\Facades\{Cache,Schema};
use Illuminate\Validation\ValidationException;

class ScheduledTaskService
{
    private array $running=[];
    private ?int $manualId=null;
    public const TASKS=[
        'release-scheduled-leads'=>['Release Scheduled Leads','Release eligible due leads after campaign, credit, consent and delivery-limit checks.'],
        'organization-lead-assignments'=>['Assign Organization Leads','Queue assignment batches for active organizations. Assignment jobs enforce team eligibility, working hours and executive limits.'],
        'client-delivery-cycles'=>['Client Delivery Cycles','Start due delivery batches and recover queued/running batches. Actual lead results are recorded in Client Delivery History.'],
        'queue-worker'=>['Process Background Queue','Process queued jobs for up to 20 seconds or 100 jobs. Individual jobs retain their own retry, permission and eligibility rules.'],
        'low-credit-alerts'=>['Low Credit Alerts','Check wallet balances and notify responsible users according to the existing credit settings.'],
        'followup-reminders'=>['Follow-up Reminders','Find due follow-ups and send reminders through configured notification channels.'],
        'operational-notifications'=>['Operational Notifications','Check operational alerts, SLA reminders and daily summaries using stored notification preferences.'],
        'team-availability'=>['Team Availability','Mark inactive executives offline using the existing team availability rules.'],
        'meta-lead-sync'=>['Meta Lead Sync','Synchronize configured Meta forms and route actual leads using integration mappings.'],
        'database-backup'=>['Database Backup','Run the existing configured database/document backup.'],
        'backup-cleanup'=>['Backup Cleanup','Remove old backups according to the configured backup retention policy.'],
    ];
    private function redact(?string $value): ?string
    {
        if($value===null) return null;
        $value=preg_replace('/((?:password|passwd|secret|access[_-]?token|refresh[_-]?token|api[_-]?key|otp|token|authorization)\s*[=:]\s*)([\x22\x27]).*?\2/i','$1********',$value);
        return AuditRedactor::clean($value);
    }
    public function events(): array
    {
        // HTTP requests do not normally load routes/console.php until the console kernel boots.
        app(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $events=[];
        foreach(app(Schedule::class)->events() as $event) if(isset(self::TASKS[$event->description])) $events[$event->description]=$event;
        return $events;
    }
    public function instrument(Event $event): void
    {
        if(!isset(self::TASKS[$event->description])) return;
        $event->before(fn()=> $this->starting($event));
        $event->after(fn()=> $this->finished($event));
    }
    public function starting(Event $event): void
    {
        if(!Schema::hasTable('scheduled_task_runs')) return;
        $key=$event->description;
        $run=$this->manualId ? ScheduledTaskRun::find($this->manualId) : null;
        if(!$run || $run->task_key !== $key) $run=new ScheduledTaskRun(['task_key'=>$key,'label'=>self::TASKS[$key][0],'expression'=>$event->expression,
            'timezone'=>(string)($event->timezone ?: config('app.timezone')),'origin'=>'scheduled']);
        $run->fill(['status'=>'running','started_at'=>now(),'command'=>$this->redact($event->command)])->save();
        $this->running[spl_object_id($event)]=['id'=>$run->id,'start'=>microtime(true)];
        if($event->command) $event->sendOutputTo(storage_path('logs/scheduled-task-'.$run->id.'.log'));
    }
    public function finished(Event $event, ?\Throwable $error=null): void
    {
        $context=$this->running[spl_object_id($event)] ?? null;
        if(!$context) return;
        $run=ScheduledTaskRun::find($context['id']);
        if(!$run) return;
        if($run->finished_at && $error) {
            $run->update(['status'=>'failed','error'=>mb_substr($this->redact($error->getMessage()),0,10000)]);
            return;
        }
        $path=storage_path('logs/scheduled-task-'.$run->id.'.log');
        $output=is_file($path) ? file_get_contents($path,false,null,0,100000) : '';
        if(is_file($path)) unlink($path);
        $code=$error ? ($event->exitCode ?: 1) : ($event->exitCode ?? 0);
        $run->update(['status'=>$code===0 ? 'completed':'failed','finished_at'=>now(),'duration_ms'=>(int)round((microtime(true)-$context['start'])*1000),
            'exit_code'=>$code,'output'=>$this->redact($output),
            'error'=>$error ? mb_substr($this->redact($error->getMessage()),0,10000) : ($code ? 'Task exited with code '.$code.'. See output for details.' : null)]);
        $this->audit($run,'scheduler.'.$run->status);
    }
    public function schedulerFailed($event, \Throwable $error): void
    {
        if(isset(self::TASKS[$event->description])) $this->finished($event,$error);
    }
    private function audit(ScheduledTaskRun $run,string $action): void
    {
        AuditLog::create(['user_id'=>$run->requested_by,'action'=>$action,'subject_type'=>ScheduledTaskRun::class,'subject_id'=>$run->id,
            'to_value'=>['task'=>$run->task_key,'origin'=>$run->origin,'status'=>$run->status,'exit_code'=>$run->exit_code],
            'context'=>['category'=>'system','status'=>$run->status==='failed' ? 'failure':'success'],'created_at'=>now()]);
    }
    public function request(string $key,User $actor): ScheduledTaskRun
    {
        abort_unless($actor->is_active && $actor->hasAnyRole(['Super Admin','super-admin']),403);
        $event=$this->events()[$key] ?? null;
        if(!$event) throw ValidationException::withMessages(['task'=>'This scheduled task is not registered.']);
        $run=Cache::lock('schedule-manual-request:'.$key,10)->get(function() use($key,$event,$actor) {
            if(ScheduledTaskRun::where('task_key',$key)->whereIn('status',['queued','starting','running'])->exists()) throw ValidationException::withMessages(['task'=>'This task is already queued or running. Check its run history.']);
            $run=ScheduledTaskRun::create(['task_key'=>$key,'label'=>self::TASKS[$key][0],'expression'=>$event->expression,
                'timezone'=>(string)($event->timezone ?: config('app.timezone')),'origin'=>'manual','status'=>'queued','requested_by'=>$actor->id,
                'command'=>$this->redact($event->command)]);
            $this->audit($run,'scheduler.requested');
            return $run;
        });
        if(!$run) throw ValidationException::withMessages(['task'=>'Another request is being processed. Please refresh.']);
        if($key==='queue-worker') $this->runManual($run->id);
        else \App\Jobs\RunScheduledTask::dispatch($run->id)->afterCommit();
        return $run->fresh();
    }
    public function runManual(int $id): void
    {
        $run=ScheduledTaskRun::findOrFail($id);
        if($run->status!=='queued') return;
        if(!$run->requester?->is_active || !$run->requester->hasAnyRole(['Super Admin','super-admin'])) {
            $this->failRun($id,'The requesting administrator is no longer authorized to run tasks.');
            return;
        }
        $event=$this->events()[$run->task_key] ?? null;
        if(!$event) { $this->failRun($id,'Task is no longer registered.'); return; }
        if(!ScheduledTaskRun::whereKey($id)->where('status','queued')->update(['status'=>'starting'])) return;
        $previous=$this->manualId;
        $this->manualId=$id;
        try {
            if(!$event->filtersPass(app())) {
                $run->update(['status'=>'skipped','finished_at'=>now(),'error'=>'Task conditions prevented execution.']);
            } else {
                $event->run(app());
                if($run->fresh()->status==='starting') $run->update(['status'=>'skipped','finished_at'=>now(),'error'=>'Another execution holds the task lock.']);
            }
        } catch(\Throwable $error) {
            // CallbackEvent can retain an earlier exception when reused by a long-lived worker.
            if($event->exitCode !== 0 || $run->fresh()->status!=='completed') {
                $this->finished($event,$error);
                if(in_array($run->fresh()->status,['queued','starting','running'])) $this->failRun($id,$error->getMessage());
            }
        } finally { $this->manualId=$previous; }
    }
    public function failRun(int $id,string $message): void
    {
        $run=ScheduledTaskRun::find($id);
        if($run && in_array($run->status,['queued','starting','running'])) {
            $run->update(['status'=>'failed','finished_at'=>now(),'error'=>mb_substr($this->redact($message),0,10000)]);
            $this->audit($run,'scheduler.failed');
        }
    }
}
