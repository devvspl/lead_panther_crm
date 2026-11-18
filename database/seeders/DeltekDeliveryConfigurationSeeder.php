<?php

namespace Database\Seeders;

use App\Models\{LeadDeliveryPlan, Organization, Project, User};
use App\Services\{LeadDistributionService, SeederRunService};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Opt-in production configuration. Does not create accounts, leads or credits. */
class DeltekDeliveryConfigurationSeeder extends Seeder
{
    public function run(): void
    {
        $tracking = app(SeederRunService::class);
        if (!$tracking->isRecording()) {
            $tracking->run(class_basename(static::class), auth()->user());
            return;
        }

        DB::transaction(function () {
            $organizations = Organization::where('name', 'Deltek')->where('status', 'active')->lockForUpdate()->get();
            if ($organizations->count() !== 1) throw new \RuntimeException('Exactly one active Deltek organization is required.');
            $organization = $organizations->sole();
            $service = app(LeadDistributionService::class);
            $recipients = $service->clientDeliveryRecipients()->where('organization_id', $organization->id)->get();
            if ($recipients->count() !== 1) throw new \RuntimeException('Deltek must have one active billing account.');
            $client = $recipients->sole();
            $admin = User::where('status', 'active')->whereHas('roles', fn ($q) => $q->whereIn('name', ['Super Admin', 'super-admin']))->orderBy('id')->firstOrFail();
            $existing = LeadDeliveryPlan::where('organization_id', $organization->id)->lockForUpdate()->first();
            $projects = Project::withoutGlobalScopes()->where('client_id', $client->id)->where('status', 'active')->where('delivery_enabled', true)->get();
            $project = $projects->firstWhere('id', $existing?->project_id);
            if (!$project && $projects->count() === 1) $project = $projects->sole();
            if (!$project) throw new \RuntimeException('Map one active delivery project to Deltek before running this seeder.');

            // Replace configuration, retaining the plan identity referenced by leads and history.
            // Stop old batches so their saved frequency/quantity cannot override this reset.
            if ($existing) $service->controlClientDelivery($existing, 'stop', $admin);
            $plan = $service->saveClientDeliveryPlan($organization, [
                'client_id' => $client->id, 'project_id' => $project->id, 'campaign_id' => null,
                'frequency' => '15_minutes', 'quantity_mode' => 'fixed', 'quantity' => 1,
                'max_cycle' => null, 'daily_enabled' => false, 'daily_limit' => null,
                'confirmed_unlimited' => true, 'automatic' => true, 'timezone' => 'Asia/Kolkata',
                'start_date' => null, 'end_date' => null, 'window_start' => '00:00', 'window_end' => '23:59',
                'daily_time' => '00:00', 'weekly_day' => 1, 'working_days' => range(0, 6),
            ], $admin);
            $service->controlClientDelivery($plan, 'resume', $admin);
            foreach ($plan->items()->where('status', 'failed')->get() as $item) $service->retryClientDelivery($item, $admin);
            $ids = $service->eligibleClientDeliveryLeads($organization->id)->pluck('id')->all();
            if ($ids) $service->enqueueClientLeads($plan->fresh(), $ids, true, $admin);
            $this->command?->info('Deltek configured: 1 lead every 15 minutes, all days, no daily cap. Run the scheduler every minute. Existing leads and history preserved.');
        }, 3);
    }
}
