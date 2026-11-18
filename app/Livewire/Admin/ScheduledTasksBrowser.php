<?php
namespace App\Livewire\Admin;
use App\Models\ScheduledTaskRun;
use App\Services\ScheduledTaskService;
use Livewire\{Component,WithPagination};
use Livewire\Attributes\Locked;
use Illuminate\Support\Facades\{Schema,DB};

class ScheduledTasksBrowser extends Component
{
    use WithPagination;
    public string $taskFilter='';
    public string $statusFilter='';
    public string $search='';
    #[Locked] public ?int $inspectedRunId=null;
    #[Locked] public ?string $inspectedTask=null;
    public bool $showDetails=false;
    public function boot(): void { abort_unless(auth()->user()?->is_active && auth()->user()->hasAnyRole(['Super Admin','super-admin']),403); }
    public function updated($name): void { if(in_array($name,['taskFilter','statusFilter','search'])) $this->resetPage('taskRunPage'); }
    public function runTask(string $key): void
    {
        $run=app(ScheduledTaskService::class)->request($key,auth()->user());
        $this->dispatch('toast',type:$run->status==='failed' ? 'error':'success',message:'Task #'.$run->id.': '.$run->status.'. See Run History for results.');
    }
    public function inspectTask(string $key): void
    {
        abort_unless(isset(app(ScheduledTaskService::class)->events()[$key]),404);
        $this->inspectedTask=$key; $this->inspectedRunId=null; $this->showDetails=true;
    }
    public function inspectRun(int $id): void
    {
        $run=ScheduledTaskRun::findOrFail($id);
        $this->inspectedRunId=$id; $this->inspectedTask=$run->task_key; $this->showDetails=true;
    }
    public function render()
    {
        $service=app(ScheduledTaskService::class);
        $ready=Schema::hasTable('scheduled_task_runs');
        $events=$service->events();
        $tasks=collect($events)->map(function($event,$key) use($ready) {
            $last=$ready ? ScheduledTaskRun::where('task_key',$key)->latest('id')->first() : null;
            $active=$ready ? ScheduledTaskRun::where('task_key',$key)->whereIn('status',['queued','starting','running'])->latest('id')->first() : null;
            return (object)['id'=>$key,'name'=>ScheduledTaskService::TASKS[$key][0],'logic'=>ScheduledTaskService::TASKS[$key][1],
                'expression'=>$event->expression,'timezone'=>(string)($event->timezone ?: config('app.timezone')),
                'next'=>\Carbon\Carbon::instance((new \Cron\CronExpression($event->expression))->getNextRunDate(now(),0,false,$event->timezone ?: config('app.timezone')))->format('d M Y H:i T'),
                'status'=>$active?->status ?: ($last?->status ?: 'Not recorded'), 'last'=>$last?->started_at?->format('d M Y H:i:s') ?: 'No recorded start',
                'duration'=>$last?->duration_ms !== null ? $last->duration_ms.' ms' : '—', 'busy'=>(bool)$active,'ready'=>$ready,'command'=>$event->command];
        });
        $runs=$ready ? ScheduledTaskRun::with('requester')->when($this->taskFilter,fn($q)=>$q->where('task_key',$this->taskFilter))
            ->when($this->statusFilter,fn($q)=>$q->where('status',$this->statusFilter))
            ->when($this->search,fn($q)=>$q->where(fn($q)=>$q->where('label','like','%'.$this->search.'%')->orWhere('error','like','%'.$this->search.'%')))
            ->latest('id')->paginate(15,['*'],'taskRunPage') : collect();
        return view('livewire.admin.scheduled-tasks-browser',[
            'ready'=>$ready,'tasks'=>$tasks,'runs'=>$runs,
            'lastScheduled'=>$ready ? ScheduledTaskRun::where('origin','scheduled')->whereNotNull('started_at')->latest('started_at')->first() : null,
            'pendingJobs'=>Schema::hasTable('jobs') ? DB::table('jobs')->count() : null,
            'failedJobs'=>Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : null,
            'selectedTask'=>$this->inspectedTask ? $tasks->get($this->inspectedTask) : null,
            'selectedRun'=>$ready && $this->inspectedRunId ? ScheduledTaskRun::with('requester')->find($this->inspectedRunId) : null,
        ]);
    }
}
