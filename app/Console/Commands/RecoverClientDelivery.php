<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Models\{Organization,LeadDeliveryPlan,User};
use App\Services\LeadDistributionService;
class RecoverClientDelivery extends Command
{
    protected $signature='client-delivery:recover {organization : Organization ID} {--confirm-sharing : Confirm sharing for eligible leads already in this organization} {--retry-failed : Return failed transfers to the normal pending queue} {--run : Process one saved delivery cycle now}';
    protected $description='Inspect or recover an existing client delivery plan without bypassing limits, credit checks or duplicate protection';
    public function handle(LeadDistributionService $service): int
    {
        $org=Organization::findOrFail($this->argument('organization'));
        $plan=LeadDeliveryPlan::where('organization_id',$org->id)->first();
        if(!$plan) { $this->error('Configure this organization\'s client delivery plan first.'); return self::FAILURE; }
        $this->info($org->name.': '.$plan->settings['frequency'].'; '.($plan->settings['quantity_mode']==='all' ? 'all available' : $plan->settings['quantity']).' per cycle; daily limit '.($plan->settings['daily_enabled'] ? $plan->settings['daily_limit'] : 'unlimited'));
        foreach($service->clientDeliveryHealth($plan)['alerts'] as $alert) $this->line(strtoupper($alert['type']).': '.$alert['message']);
        if(!$this->option('confirm-sharing') && !$this->option('retry-failed') && !$this->option('run')) return self::SUCCESS;
        $actor=User::where('status','active')->whereHas('roles',fn($q)=>$q->whereIn('name',['Super Admin','super-admin']))->find($plan->created_by);
        if(!$actor) { $this->error('The plan administrator is no longer active. Update the plan through Super Admin.'); return self::FAILURE; }
        if($plan->state!=='active') { $this->error('Resume the plan through Super Admin first.'); return self::FAILURE; }
        if($this->option('retry-failed')) {
            $failed=$plan->items()->where('status','failed')->get();
            foreach($failed as $item) $service->retryClientDelivery($item,$actor);
            $this->info($failed->count().' failed transfer(s) returned to the pending queue.');
        }
        if($this->option('confirm-sharing')) {
            $ids=$service->eligibleClientDeliveryLeads($org->id)->pluck('id')->all();
            if($ids) {
                $result=$service->enqueueClientLeads($plan,$ids,true,$actor);
                $this->info($result['queued'].' eligible lead(s) enrolled with sharing confirmation.');
            }
        }
        if(!$this->option('run')) return self::SUCCESS;
        $run=$service->startClientDeliveryCycle($plan->id,true);
        if(!$run) { $this->warn('No cycle can start: check ready queue, working hours and daily capacity.'); return self::FAILURE; }
        $remaining=$service->processClientDeliveryRun($run->id,1);
        if($remaining) \App\Jobs\ProcessClientDeliveryRun::dispatch($run->id);
        $failed=$run->items()->where('status','failed')->latest('updated_at')->first();
        if($failed) { $this->error($failed->failure_reason); return self::FAILURE; }
        $this->info('Batch #'.$run->id.': '.$run->items()->whereIn('status',['sent','accepted'])->count().' delivered. Next delivery: '.($plan->fresh()->next_run_at?->toIso8601String() ?: 'manual only'));
        return self::SUCCESS;
    }
}
