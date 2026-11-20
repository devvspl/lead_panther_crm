<?php

/**
 * ============================================================================
 * PRODUCTION BACKBONE & SYSTEMD/SUPERVISOR CONFIGURATION REQUIREMENTS
 * ============================================================================
 *
 * Shared hosting: run schedule:run every minute; the bounded queue worker below is included.
 * VPS: a Supervisor worker can additionally keep background jobs processing continuously.
 *
 * 1. CRON SCHEDULER (Runs every minute to trigger scheduled tasks):
 *    * * * * * cd /var/www/lead-panther-crm && php artisan schedule:run >> /dev/null 2>&1
 *
 * 2. SUPERVISOR QUEUE WORKER (Keeps async job workers alive):
 *    [program:leadpanther-worker]
 *    process_name=%(program_name)s_%(process_num)02d
 *    command=php /var/www/lead-panther-crm/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
 *    autostart=true
 *    autorestart=true
 *    user=www-data
 *    numprocs=4
 *    redirect_stderr=true
 *    stdout_logfile=/var/www/lead-panther-crm/storage/logs/worker.log
 * ============================================================================
 */

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule 1: Process Background Queue Jobs (Every Minute - for Shared Hosting / Hostinger)
// The bounded shared-hosting worker is registered LAST so newly created jobs run this minute.

// Schedule 2: Check Low Credit Balances & Notify Client Admins (Hourly)
Schedule::call(function () {
    \App\Models\Lead::withoutGlobalScopes()->where('delivery_pending', true)
        ->whereNotIn('status', ['duplicate', 'rejected', 'invalid'])
        ->whereNotIn('current_stage', ['duplicate', 'rejected', 'invalid'])
        ->where('delivery_at', '<=', now())
        ->where(function ($q) { $q->whereNull('delivery_failed_at')->orWhere('delivery_failed_at', '<=', now()->subMinutes(5)); })
        ->chunkById(100, function ($leads) {
            foreach ($leads as $lead) {
                app(\App\Services\LeadDistributionService::class)->releaseDelivery($lead->id);
            }
        });
})->name('release-scheduled-leads')->everyMinute()->withoutOverlapping();

app(\App\Services\ScheduledTaskService::class)->command('low-credit-alerts','credits:check-low-balances')->hourly();

Schedule::call(function () {
    \App\Models\Organization::whereNotNull('assignment_config')->where('auto_assign_enabled',true)->where('status','active')
        ->chunkById(100, function ($organizations) {
            foreach ($organizations as $organization) \App\Jobs\AssignOrganizationLeads::dispatch($organization->id);
        });
})->name('organization-lead-assignments')->everyMinute()->withoutOverlapping();

// Recover queued/running cycles as well as starting plans due now. Per-plan DB locks prevent duplicate cycles.
Schedule::call(function () {
    $service = app(\App\Services\LeadDistributionService::class);
    \App\Models\LeadDeliveryPlan::where('state', 'active')->chunkById(100, function ($plans) use ($service) {
        foreach ($plans as $plan) {
            $run = $plan->runs()->whereIn('status', ['queued', 'running'])->first() ?? $service->startClientDeliveryCycle($plan->id);
            if ($run) \App\Jobs\ProcessClientDeliveryRun::dispatch($run->id);
        }
    });
})->name('client-delivery-cycles')->everyMinute()->withoutOverlapping();

// Schedule 3: Send Follow-up Due Reminders via WhatsApp/SMS/Email/In-App (Every 15 Minutes)
app(\App\Services\ScheduledTaskService::class)->command('followup-reminders','followups:check-due')->everyFifteenMinutes();
app(\App\Services\ScheduledTaskService::class)->command('operational-notifications','notifications:check-operational')->everyMinute()->withoutOverlapping();

// Schedule 4: Auto-offline Inactive Team Members (Every 5 Minutes)
app(\App\Services\ScheduledTaskService::class)->command('team-availability','team:auto-offline')->everyFiveMinutes();

// Schedule 5: Discover/save Meta forms and sync leads (Every 10 Minutes).
app(\App\Services\ScheduledTaskService::class)->command('meta-lead-sync','meta:sync-leads')->everyTenMinutes()->withoutOverlapping();

// Schedule 6: Daily Backup of Database + Documents Disk
app(\App\Services\ScheduledTaskService::class)->command('database-backup','backup:run')->daily();

// Schedule 7: Weekly Cleanup of Old Backups
app(\App\Services\ScheduledTaskService::class)->command('backup-cleanup','backup:clean')->weekly();

app(\App\Services\ScheduledTaskService::class)->command('queue-worker','queue:work',['--stop-when-empty'=>true,'--tries'=>3,'--max-time'=>20,'--max-jobs'=>100])->everyMinute()->withoutOverlapping();

foreach (app(\Illuminate\Console\Scheduling\Schedule::class)->events() as $task) {
    if (isset(\App\Services\ScheduledTaskService::TASKS[$task->description])) {
        $task->withoutOverlapping(15);
        app(\App\Services\ScheduledTaskService::class)->instrument($task);
    }
}
