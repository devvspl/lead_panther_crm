# Internal lead processing and client handover

## Existing architecture and gaps

Leads already belong to a client user, whose organization owns the settings. Campaigns belong to projects; projects also reference client users. LeadObserver allocates delivery slots, LeadCreated reserves credits, CreditReserved invokes the existing client assignment rules, and the scheduled delivery processor releases deferred leads. Notifications, audit logs, client panel scopes and SLA reports already existed.

The missing pieces were an organization-level internal processing gate, a separate internal assignee and lifecycle, internal calling/follow-up history, an authorized internal queue, and a transaction-safe handover. Reusing the client stage or assigned_to for verification would expose leads prematurely and contaminate client response metrics. The existing organization-agent lookup also had a cross-organization fallback; this has been removed.

## Data and configuration

Migration: `2026_11_15_000001_add_internal_lead_processing.php`.

- Organization: enabled flag, selected platform team, JSON configuration containing member IDs, available statuses, forwarding statuses, assignment method and automatic-forward flag.
- Lead: `internal_processing_pending`, separate from existing scheduled-delivery fields.
- `internal_processing_statuses`: canonical business status dictionary stored in the database. The migration inserts status definitions only, not example users, leads or organization settings.
- `internal_lead_processing`: one record per lead, enforced by a unique lead_id. Tracks internal assignee, status, verification, attempts, remarks, contact/follow-up times, requested and completed handovers, actors and errors.
- `internal_lead_processing_events`: actual action history, including call outcomes and remarks. Important actions also append existing audit records.

Super Admin configures processing in the existing Organization Overview tab. Eligible teams are owned by a platform Super Admin or that administrator's organization. Callers must be active, selected members of that platform team. Assignment supports round robin, least active internal workload and manual assignment. Client team members cannot be selected as platform callers.

The configuration controls new lead routing. Disabling it leaves existing internal records held and actionable so they are not silently delivered or discarded. Changing selected callers revokes former callers' access; Super Admin can reassign outstanding records to current eligible callers.

## Workflow

1. New leads for enabled organizations retain their actual client ownership, but are held without a client delivery slot or credit charge. LeadObserver creates the internal record atomically with lead insertion. Repeated LeadCreated handling reuses the same record.
2. Authorized staff use Internal Processing Queue in the existing Leads page. Super Admin can inspect all internal records; callers can process only their own assignments while membership and organization configuration remain valid.
3. Internal calls, statuses and follow-ups never modify client calls, first_response_at or client pipeline stages. A connected call and recorded remarks are required for verification. New call attempts invalidate previous verification. Invalid, duplicate and not-interested states block caller actions and forwarding; Super Admin can reopen them through status management.
4. Manual forwarding requires confirmation, verified state and a configured eligible status. Automatic forwarding uses the same service and checks.
5. Handover locks project, lead and processing record in the same order as the delivery processor. It snapshots existing effective campaign/project delivery settings, then invokes existing credit and assignment processing. If a limit, interval, pause or credit issue blocks release, the lead remains hidden from the client and retains its internal history. Existing scheduler retries eligible queued leads; credit failures retry after the existing backoff.
6. Successful delivery updates the lead and processing record in the same transaction, records who requested the handover, writes history and audit events, and sends existing-system notifications. Duplicate requests reuse the same handover and cannot charge twice.
7. Clients see the lead in their normal panel after successful delivery. The existing detail drawer adds verification, internal remarks and call history, attempt count, last contact, handover time, client assignee and current client stage. Client SLA/report calculations start at successful internal handover; direct-delivery SLA behavior is preserved.

## Notifications and statistics

Internal received/assigned, due follow-up, verified, forwarded and forwarding-failure notifications use existing database persistence and broadcast/polling infrastructure. Event keys prevent duplicate retry/reminder notifications. Before handover, client users receive no internal delivery notices. Staff notifications belong to the staff recipient's organization; opening them rechecks authorization for the actual internal record. Super Admin notifications retain the source client organization.

Overview statistics query real leads and processing records. Date inputs select the received cohort using the organization's delivery timezone. Counts show that cohort's current processing state; total received also includes direct deliveries. Internal queue date inputs explicitly use the application timezone. No dashboard sample values are inserted.

## Verification

`InternalLeadProcessingTest`: 16 tests / 104 assertions, using real isolated database rows for two client organizations plus the platform team. Covers enabled/disabled routing, round robin/capacity/manual assignments, calls and follow-ups, verification validation, custom forwarding eligibility, manual/automatic handover, failed-credit retry, duplicate prevention, limits/interval scheduling, client visibility, notification recipient isolation and links, reminder deduplication, revoked membership, date-filtered statistics, Livewire settings/actions, SLA report and dashboard rendering.

Focused regression run: 108 tests / 634 assertions passed, covering this workflow plus delivery schedules, role-based lead management, notification/audit management, communication, assignment, replacement, SLA and all reports/Excel exports. Frontend production build passed.

Broader legacy checks exposed unrelated issues: five RoleDashboard tests fail while the old LeadSeeder assigns campaign/lead owners from different organizations; CreditReservation and EndToEndLeadWorkflow each expect the obsolete `reserve` transaction type instead of existing `lead_consumption`. The focused run excludes the previously known RoleBasedLeadManagement missing-followup-route navigation case. These are not claimed as passing.

Testing uses SQLite and PHP 8.2 locally. Transaction/row-lock code is in place, but simultaneous MySQL worker contention and the hosted PHP 8.3 environment have not been exercised in this session.

## Deployment

Deploy the changed code and frontend assets, run `php artisan migrate --force`, clear/rebuild the application's deployment caches as usual, and restart long-running queue workers. The existing scheduler must continue running `release-scheduled-leads` every minute and `followups:check-due` every fifteen minutes. No new queue service or scheduler is introduced. Do not run demo seeders in production. No hosted deployment or production data migration was performed in this session.

## Changed files

New: the migration; InternalProcessingStatus, InternalLeadProcessing and InternalLeadProcessingEvent models; ProcessesInternalLeads service concern; InternalProcessingSettings and InternalProcessingQueue Livewire components and their views; InternalLeadProcessingTest; this document.

Extended: LeadDistributionService, LeadPricingService, NotificationDeliveryService, LeadJourneyService; Lead, Organization, CreditTransaction and ClientScope; LeadObserver; ReserveCreditOnLeadCreated; CheckDueFollowupsCommand; NotificationController; LeadKanban, LeadDetail, DashboardShell, ReplacementRequestForm, SlaDashboard and SalesExecutivePerformance; DashboardReportExport; organization-detail, lead-kanban and lead-detail views; LeadDistributionTest and RoleBasedLeadManagementTest fixtures/assertions.
