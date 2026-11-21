# Cron, queues and operational processing

## Production model

`routes/console.php` defines schedule. Run Laravel schedule:run every minute using deployed app directory and compatible PHP CLI. No reviewed public artisan-schedule-run HTTP route exists.

```cron
* * * * * cd /absolute/path/to/lead-panther && /absolute/path/to/php artisan schedule:run >> storage/logs/scheduler-cron.log 2>&1
```

Example only: use actual host paths. Shared hosting already schedules a bounded queue worker. VPS can use Supervisor/systemd workers; coordinate visibility timeouts/overlap before combining modes. No production cron or job was changed by this review.

## Task inventory

| Key | Frequency | Actual work |
|---|---|---|
| release-scheduled-leads | Every minute | Due pending eligible leads; retry failures after 5 min; releaseDelivery |
| organization-lead-assignments | Every minute | Dispatch AssignOrganizationLeads for active configured/enabled orgs |
| client-delivery-cycles | Every minute | Start due plans/recover queued or running runs; dispatch processing |
| queue-worker | Every minute, registered last | queue:work --stop-when-empty --tries=3 --max-time=20 --max-jobs=100 |
| low-credit-alerts | Hourly | credits:check-low-balances |
| followup-reminders | Every 15 min | followups:check-due plus internal reminders |
| operational-notifications | Every minute | notifications:check-operational: SLA, prior-day summaries |
| team-availability | Every 5 min | Schedules team:auto-offline, but actual command is teams:auto-offline: mismatch |
| meta-lead-sync | Every 10 min | meta:sync-leads: forms and leads |
| meta-ad-accounts-sync | Every 10 min | meta:sync-ad-accounts: dispatch independent sync jobs |
| database-backup | Daily | backup:run |
| backup-cleanup | Weekly | backup:clean |

Instrumented tasks receive withoutOverlapping(15) through registration loop; several already request overlap protection. Shared cache must support functioning locks. A green task only confirms execution, not successful delivery of each lead.

**Confirmed defect:** AutoOfflineInactiveTeams uses signature teams:auto-offline and marks all active executives inactive without an inactivity check. Do not simply fix scheduled spelling or run it manually; see KNOWN_ISSUES. Status inactive revokes login, not just availability.

## Job map

| Class | Important behavior |
|---|---|
| ProcessInboundLeadJob | Routing/mappers/duplicate checks/metadata; failures logged then rethrown; Meta ID cache lock |
| AssignOrganizationLeads | Calls org assignment processor |
| ProcessClientDeliveryRun | After-commit dispatch; tries 3; timeout 120s; backoff 30/120/300s; chunks and requeues |
| SyncMetaAdAccount | Per-account job/cache overlap protection; tries 4; timeout 60s; backoff 300/900/1800s; persisted cursor continuation |
| SendBrowserPush | Authorized delivery with subscription/notification tracking |
| SendNotificationEmail | Branded queued email |
| RunScheduledTask | Allowlisted tracked Super Admin task request |
| RunGitSyncJob / RunBackupJob | Administrative background actions |

Queue config defaults database; Redis/SQS/etc are configurable. PHPUnit uses sync, so it does not prove actual worker concurrency behavior. Database queue retry_after defaults90s while delivery job timeout is120s: coordinate before concurrent production workers. Worker max-time 20s is not a per-job 20s hard limit.

Business delivery failures may not throw a queue exception: failed items are retried by automatic plan cycles using bounded exponential delay and persisted attempt history. Queue exception retries alone do not describe delivery recovery.

## Monitoring/recovery

ScheduledTaskService uses in-process Artisan calls, scheduled_task_runs and redacted output/status/duration/exit history. AppServiceProvider listens for scheduler failures. UI requests are restricted to TASKS registry and reject duplicate queued/running requests. Temporary task log content is copied into tracked history then removed.

Read-only deployment checks: schedule:list, selected queue/cache config, scheduled_task_runs, failed_jobs, webhook_logs, delivery errors and item attempt histories. `RecoverClientDelivery` exposes client-delivery:recover; inspect command options before using. Older screenshots saying Queue All Eligible predate automatic enrollment.

Do not restart all cron entries or blanket retry jobs to solve missing approvals/credits/mappings. Inspect state in LEAD_DISTRIBUTION_FLOW diagnostic order.

## SLA timing

Executive deadline = assignment time + saved response minutes(default 15). CheckWebNotificationAlerts scans delivered assigned nonterminal leads without response. Warning starts at due minus min(5,max(1,ceil(response_minutes*0.2))) minutes; breach is after due. Event keys include assignment identity, preventing repeated per-minute alerts. Daily summaries are considered every minute but deduplicated per recipient/org/date.

Follow-up checks skip unavailable/pending-delivery leads and deduplicate event keys. Low-credit alerts skip unreconciled accounts and use saved organization thresholds. The scheduled team-availability command is defective as documented above; do not equate last login, availability and user active status.

## Dependencies and deployment cautions

Migrations applied, writable storage/bootstrap cache, correct PHP extensions/binary, usable database/cache locks, configured mail/push and valid Meta permissions. Timezone is configuration-driven; plan/calendar timestamps convert through app timezone. Restart long-lived workers after deployments. Backups require configured disk/dump tooling and retention policy. No guarantees about deployed workers/cron/backup completion were inferred from source.

Integer-credit migration is forward-only; pause old writers/workers for schema transition, back up and reconcile old accounts before resuming delivery. Automatic rollback is disabled intentionally. See docs/lead-credits-migration.md.
