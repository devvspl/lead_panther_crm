# Business workflows

Read LEAD_DISTRIBUTION_FLOW for delivery detail and USER_ROLES_PERMISSIONS for boundaries. “Client” means a billing user under an organization unless stated otherwise.

## Client registration and management

`OrganizationManager::createOrganization` validates name/status and creates the organization only. Users are created separately through organization/user actions with role assignment and a password-reset invitation link. `OrganizationDetail` owns overview/profile, members, projects, leads, credits, campaign/delivery and activity/audit tabs. Directory metrics query actual org records and last login from auth.login audit history.

`Client` is a User compatibility subclass: missing email/password are generated on creation; absent roles are assigned Builder/Developer. `OrganizationManager::toggleStatus` changes organization active/suspended. This does not itself prove user sessions are blocked: login middleware checks user status separately. Test both layers before promising organization-wide suspension enforcement.

Public Volt registration validates a generic user, emits Registered, logs in and redirects. It does not explicitly provision organization, billing owner, role or approval workflow. Treat self-registration onboarding as incomplete until its downstream state is verified.

## Projects and campaigns

Projects belong to client user; campaigns belong to project and lead source. Projects carry active status, property metadata, credit override and delivery defaults. Campaign override values may inherit project delivery settings. Lead has no persisted project_id; campaign identifies project, while a compatibility setter may create/select General Campaign when old code sets project_id.

Meta form mappings preserve portal account/form identity, campaign/project/client associations. Multiple Ad Account remote campaigns use account-specific remote identities; fallback name-only mapping in old intake remains a risk. Prefer explicit saved mappings.

## Lead creation/import

LeadKanban contains UI creation/actions; BulkLeadUpload parses mapped CSV/Excel, validates mobile lengths, normalizes Indian ten-digit phones, validates optional row fields, checks 90-day duplicates and records counts/errors in UploadBatch. Upload rows are held in component memory and processed under a project transaction; large imports need performance review.

Webhook/polling intake records raw attribution in LeadMetadata. ProcessInboundLeadJob uses Meta/Google/portal/owned mappers, fetches Meta fields when needed, applies form mapping, checks Meta leadgen identity/cache lock and 90-day client mobile/email duplicate logic. Duplicate inputs attach metadata rather than charge/create another lead. Some missing values are invented defaults, documented under known issues.

## Assignment and team management

LeadDistributionService owns assignment; organization assignment config takes precedence over old project rules. Delivered, unassigned, eligible leads are distributed on creation/release or scheduled org batches. Member active status, membership, availability, working hours/days, method and executive daily quota matter. Client delivery interval is independent.

SalesTeam ownership is polymorphic (Organization/User, historical types may exist); SalesTeamMember links users and holds active/capacity/priority state. Team leader access uses leader_id, org ownership and permission checks. BuilderTeamManager manages scoped teams and executive membership; removal requires reassigning existing leads and preserves accounts. New user invitations are reset-password links, not an external identity provider.

## Lead stages and activity

Lead::normalizeStage/stageValues support aliases. Typical sequence: new/assigned → contacted → interested → follow_up → meeting_scheduled → site_visit → negotiation → booking → payment_received → closed_won. Lost/invalid/unreachable/replaced are terminal paths. This is not an unrestricted transition graph: permittedSalesActions returns stage-specific operation names, and LeadDetail/LeadKanban validate actions.

Calls, outbound communications, followups, meetings, visits, negotiation, bookings and payments live in related records. Executive current SLA is distinct from legacy first_response_at. FirstResponseObserver records qualifying activity through ExecutiveResponseService and also updates older response tracking. Some legacy predicates differ; do not merge them without tests.

Financial booking/payment/proposal displays and form components are Super Admin restricted in the current credit-system changes. These domain money fields were preserved, not converted into lead credits. Operational team screens suppress money/source attribution.

## Internal verification and client handover

ProcessesInternalLeads configures platform-owned teams/executives, allowed statuses, forward statuses, method and auto-forward. New internal leads remain hidden from client panels. Assigned internal executives can log call/status/remarks/followup/verification only through internalQueueQuery and canProcessInternal.

Verification needs a connected call/last_contacted_at and calling remarks. Eligible verified status plus handover request marks the lead for normal client delivery. Auto-forward can request handover when configured. Updates are blocked after requested/forwarded handover; failed forwarding is recorded, not silently treated as delivery. Disabling internal processing retains historical records and skips the approval gate for current client delivery.

## Lead credits

Current schema uses integer entitlements. LeadPricingService priority: project > supplied team > organization > global general setting; default 1. Existing whole-number overrides survive migration; a saved10 costs10 credits, not1. A caller must actually supply team context for team pricing to apply.

Successful delivery consumes credits atomically through CreditTransaction::reserveForLead; unique delivery charge identity plus row locks prevent repeats. Failed/pending/duplicate/undelivered leads consume nothing. Used credits are active nonlegacy lead-linked consumption/reserve/deduct minus lead-linked refunds, minimum0. Total = remaining + net used; it is not a historical gross recharge total.

LeadCreditService lets active Super Admin add/deduct credits with remarks, validates integer quantities, rejects insufficient deductions, and appends ledger/audit rows. RechargeApprovalQueue locks pending requests and approves integer quantities once. Requests store quantity in compatibility field amount; no money conversion.

Migration preserves decimal history in legacy_* columns, marks old transactions is_legacy, starts existing wallets at0/unreconciled. Delivery is held until Super Admin confirms usable allocation. Reconciliation is a separate correction, rejects stale balance, and preserves originals. Disputes record/resolve with optional linked corrective entry; they do not rewrite the disputed row.

Reconciliation UI now includes legacy wallet owners without current billing-role match, opens by default and selects the org client. Mark as Consumption explicitly deducts a chosen quantity for an old successfully delivered legacy lead; repeats/undelivered/already-accounted leads are rejected and a new linked consumption/audit is written. Reconcile allocation before applying such historical consumption; do not double-subtract a quantity already excluded from a verified net remaining figure. Details: `docs/lead-credits-migration.md`.

## SLA and notifications

ExecutiveResponseService assignment due = assigned_at + org executive_response_minutes (default 15). Qualifying current-assignee activity records current response only once; pending/internal/old/future response timestamps are excluded. LeadAssignment retains historical response deadline/time. Reports and lead badges expose pending/met/breached status. Operational command sends approach/breach alerts using assignment-specific dedup keys; follow-up command sends due reminders.

NotificationDeliveryService writes authorized deterministic in-app notifications in the business transaction; push/email/broadcast dispatch after commit. Preferences and recipient org/lead visibility are rechecked. WhatsApp/SMS are logging simulations. Browser push requires VAPID setup, permission and subscription. Gemini is not implemented in reviewed app/config.

## Replacements

Client history/detail actions create pending requests with reason/remarks and SLA eligibility. Super Admin ReplacementQueue resolves. LeadReplacement::approve locks original lead, sets replaced status/history, refunds only actual active nonlegacy charge into a reconciled wallet once, and creates a replacement lead linked to request. It currently copies original contact data; it is a placeholder replacement identity, not proof a new prospect was sourced. Rejection records resolution. Archived leads are filtered by archive-aware relations.

## Archive/restore and retention

Active Super Admin can archive with reason. LeadArchiveService updates archive metadata without deleting stage/assignment/activity, detaches queued transfer items from in-flight runs and audits. ArchiveAwareBuilder hides lead and related records from normal queries even without tenant scopes. Restore clears archive metadata and sets archive_assignment_hold unless explicit reassignment was chosen; historical state stays intact. Purge requires archive and confirmation UI; service refuses leads with credit/payment/transfer evidence, and restrictive FKs fail transaction safely. Raw DB queries need separate archive constraints.

## Reports, exports, audits and deployment

ReportsContainer mounts source, SLA, replacement, followup, revenue and executive reports. Source/revenue are independently restricted. Reports use operational tables; source credit usage uses actual ledger rather than current price multipliers. BaseStyledExport/ExportService apply shared Excel styling and lower-role monetary restrictions. Every export still needs correct source query scope.

AuditLog is append-only through model/builder and uses AuditContext/Redactor. Observers capture model changes; middleware/auth subscriber add request/auth/access events. Explicit service audits record delivery, credit, internal processing and archive actions. DB::table writes can bypass Eloquent observers; do not assume automatic audit for arbitrary SQL.

GitSync queues administrative repository actions through GitSyncService, safety branches and audited follow-up commands. BackupStatus/RunBackupJob use configured backup package/disks. Tracked seeder service records row changes separately. Never run demo/reset/cleanup seeders or Git deploy as a diagnostic side effect.
