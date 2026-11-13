# Campaign Details & Delivery correction

## Existing architecture and findings

`routes/web.php` maps `admin.organizations.show` to the existing OrganizationDetail Livewire component. Organization owners are users: `projects.client_id` references a user belonging to an organization. Campaigns reference projects; leads reference campaigns and recipient users. The queue is stored on leads (`delivery_pending`, `delivery_at`, `delivered_at`), rather than in a separate delivery service or queue table.

Webhook processing (`ProcessInboundLeadJob`), Meta sync (`MetaLeadSyncService`), and manual lead entry dispatch `LeadCreated`. `ReserveCreditOnLeadCreated` reserves credits through `CreditTransaction`; `CreditReserved` triggers existing distribution, assignment and notification listeners. The minute scheduler releases held leads through `LeadDistributionService`. Inbound and Meta ingestion already deduplicate before creating lead records.

The reviewed tab used real database counts, not dummy records. Its defects were incomplete statistics, min:1 validation rejecting unlimited delivery, no delivery pause control, shared project quotas for inheriting campaigns, fallback from delivery timestamps to creation timestamps, missing organization timezone support, missing failure tracking, untracked immediate deliveries, and release-time settings changing already queued behavior. Organization-side manual entry also bypassed the queue. Assignment was recorded twice by the service and its credit listener.

## Corrected behavior

- Each campaign independently inherits the project's daily limit and interval unless that individual field is overridden. Zero is unlimited/immediate; null campaign values inherit.
- Project delivery_enabled pauses releases independently of project lifecycle status. Inactive projects and inactive campaigns also hold delivery.
- Organization delivery_timezone is saved and validated. Until configured it uses the application's configured timezone, which the UI explicitly displays. Daily ranges use local midnight, converted to the application's database timestamp timezone.
- New scheduled leads save a configuration snapshot. Existing queued timestamps are never rewritten by settings saves. The new migration snapshots currently queued settings without inventing historical successes.
- Planning reserves future slots for pending leads; daily consumption counts only actual delivered_at records. Failed, duplicate, rejected and invalid records do not consume successful capacity. Undelivered rejected records remain hidden from customer panels.
- Releases acquire the project row lock, then the lead row lock, then use the existing wallet transaction. Repeated releases do not charge or assign twice. Catch-up processing still respects interval/daily restrictions. Database exceptions roll back the charge before recording an error. Failed queued records are retried by the existing minute scheduler after a five-minute cooldown.
- The same service calculates project/campaign totals, successful deliveries today, capacity, queued and currently failed lead counts, and next/last delivery timestamps. Project capacity is summed across campaigns; it is unlimited when any campaign is unlimited. Overdue saved slots are labelled as waiting for processing or eligibility.
- Failed count means distinct currently failed leads awaiting retry, not lifetime attempt count. Historical leads without successful-delivery records are not counted as delivered.

## Changed files

- `app/Services/LeadDistributionService.php`: effective configuration, per-campaign planning, snapshot enforcement, timezone boundaries, failure tracking, release transactions and live statistics.
- `app/Livewire/Admin/OrganizationDetail.php`: scoped settings/timezone saves, validation/toasts, live data, and manual ingestion connected to the existing event flow.
- `resources/views/livewire/admin/organization-detail.blade.php`: project defaults, campaign inheritance/overrides, effective values and live statistics within the existing design.
- `resources/views/components/ui/delivery-statistics.blade.php`: shared presentation for the tab's database statistics.
- `app/Models/Lead.php`: snapshot and failure timestamp casts.
- `app/Listeners/ReserveCreditOnLeadCreated.php`: immediate/manual events use tracked delivery too.
- `app/Listeners/AssignLeadAfterCreditReserved.php`: reuse the service's assignment record instead of inserting a duplicate.
- `routes/console.php`: existing scheduled processing skips rejected records and applies failed-attempt retry cooldown.
- `database/migrations/2026_11_13_000001_correct_delivery_configuration.php`: organization timezone, project delivery enable flag, lead snapshots/failure state, existing queue snapshot migration.
- `tests/Feature/Leads/LeadDeliveryScheduleTest.php`: real database configuration/delivery/statistics tests.
- `tests/Feature/Leads/RoleBasedLeadManagementTest.php`: lifecycle fixture completes real delivery before opening a campaign lead in the sales panel.

## Verification and limits

43 tests / 238 assertions passed across delivery scheduling, existing distribution, and role-based lead management, excluding the existing missing-followup-route regression. Verified saves/reloads, overrides/inheritance, zero values, pauses, independent limits, intervals/catch-up, organization midnight rollover, queue snapshots, real statistics, failed-credit handling, transaction rollback, single charging/assignment/notification, stale event replay, and organization isolation.

The excluded existing test removes `followups.index` and fails because NavigationConfig generates that route unconditionally. It is outside this correction and was not changed.

Tests use the project's isolated SQLite test database. Production MySQL/MariaDB row-lock concurrency has not been stress-tested; the release path implements project/lead row locking and database transactions. No live-host deployment or production database changes were performed.

## Deployment

Run `php artisan migrate --force` with the deployed revision, then `php artisan optimize:clear`. Keep the existing cron invoking `php artisan schedule:run` every minute. Configure the organization's delivery timezone before scheduling new deliveries. Existing queued timestamps and their migrated settings are retained. No replacement workspace service or additional daemon is introduced.
