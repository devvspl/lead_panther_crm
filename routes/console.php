<?php

/**
 * ============================================================================
 * PRODUCTION BACKBONE & SYSTEMD/SUPERVISOR CONFIGURATION REQUIREMENTS
 * ============================================================================
 *
 * This project requires TWO continuous background processes in production:
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

// Schedule 1: Check Low Credit Balances & Notify Client Admins (Hourly)
Schedule::command('credits:check-low-balances')->hourly();

// Schedule 2: Send Follow-up Due Reminders via WhatsApp/SMS/Email/In-App (Every 15 Minutes)
Schedule::command('followups:check-due')->everyFifteenMinutes();

// Schedule 3: Auto-offline Inactive Team Members (Every 5 Minutes)
Schedule::command('team:auto-offline')->everyFiveMinutes();

// Schedule 4: Daily Backup of Database + Documents Disk
Schedule::command('backup:run')->daily();

// Schedule 5: Weekly Cleanup of Old Backups
Schedule::command('backup:clean')->weekly();
