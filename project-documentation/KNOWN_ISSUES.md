# Known issues, risks and verification gaps

## How to read findings

**Confirmed source** = directly observed code, not an exploited production incident. **Needs verification** = credible risk whose effect depends on deployment/data/call path. **Historical test result** = observed in earlier implementation work in this chat, not rerun during this read-only documentation task. No fixes are applied here.

## Security and authorization priorities

| ID / priority | Evidence and implication | Next verification/fix |
|---|---|---|
| SEC01 / critical | Confirmed: MetaWebhookController POST reads secret but never computes body HMAC; checks invalid-marker substring only. Google/Portal/Owned controllers similarly do not compare to configured credentials and allow absent credentials. | Implement real constant-time signature/shared-key checks before processing; test absent/wrong/replayed/valid signed payloads and batch splitting. |
| SEC02 / high | Confirmed: routes/api.php exposes POST /api/leads/manual without auth or explicit throttle; ManualLeadController creates account and dispatchSync unvalidated request. | Define authenticated integration contract, authorize target tenant, validate payload and rate limit before ingestion. |
| SEC03 / high | Confirmed: ClientScope compares client_id with user.id OR organization_id; Lead::panelQuery explicitly says owner IDs are users. DocumentDownloadController similarly compares path client ID to organization ID. Effect depends on IDs/role/path. | Test colliding IDs across organizations and signed document ownership; converge on explicit owner-user membership logic. |
| SEC04 / high | Confirmed: ProcessInboundLeadJob unmapped fallback picks first Client/User/project; fallback Campaign::firstOrCreate uses name alone. This can misroute a lead or collide across tenants. Explicit multi-account remote mapping does not cover every fallback. | Require unambiguous scoped mappings; quarantine unknown inputs; test same-name campaigns across accounts/orgs. |
| SEC05 / medium | Confirmed: LogsWebhookPayload only redacts a short top-level key list. Raw metadata/logs can retain nested/alternate sensitive fields; external exception messages may contain tokenized URLs in older Meta paths. | Recursive redaction, retention/access policy, test nested payloads and sanitized exception outputs. No secrets were read or reproduced here. |
| SEC06 / medium | Confirmed: auth middleware checks users.status only; OrganizationManager suspension updates organization.status. Parent-org suspension does not explicitly revoke all sessions in that middleware. | Decide suspension semantics; test login/existing session/API with active user under suspended org. Delivery already has separate organization checks. |
| SEC07 / medium | Confirmed: User role normalization can map client to Executive, while NavigationConfig retains client role branch. Routes/manual role predicates are not uniform. | Characterize legacy aliases/multi-role data with direct action tests before normalization cleanup. |
| SEC08 / review | GitSyncService constructs authenticated remote URLs passed as Process arguments; has powerful reset/seed/follow-up actions. Document endpoints also rely on signed links and older path checks. | Review host process visibility, token redaction, explicit authorization/confirmation and least-privilege deployment identity. Do not run these actions during audit. |

## Operational command defect (confirmed, high priority)

`routes/console.php` schedules `team:auto-offline`; AutoOfflineInactiveTeams declares `teams:auto-offline`. The scheduled name does not match the registered command. More seriously, its handle method updates every active sales-executive-role user to status inactive without any last-seen/inactivity predicate. That status blocks login through EnsureAccountIsActive. **Do not fix only the spelling or manually run the plural command**: define availability versus account-status behavior and an actual inactivity threshold first. This review did not execute either command.

## Incomplete or inconsistent behavior

- **Confirmed: WhatsApp/SMS are simulations.** AppServiceProvider binds LogWhatsAppGateway/LogSmsGateway; NotificationDeliveryService dispatch logs and returns success. Delivery statuses are not actual provider receipts. Real provider/auth/template/receipt/retry integration remains work.
- **Confirmed search absence: Gemini.** No Gemini or generativelanguage reference was found in app/config. Separately hosted code was not inspected; do not invent an implemented AI workflow.
- **Confirmed scaffold:** LeadActivity has no model implementation beyond archive hiding; no lead_activities migration/reference was found. Merely registering its audit observer does not create an activity store. Check whether any dynamic path uses it before removal.
- **Confirmed: empty listener scaffolds.** HandleBookingCreated, HandleCreditReserved, HandleFollowupCreated, HandleLeadAssigned, HandleLeadCreated, HandleNotificationSent, HandlePaymentRecorded and HandleSiteVisitLogged have empty handle bodies. Functional behavior exists in other observers/listeners; determine event registration before deleting or filling them.
- **Confirmed: self-registration lacks explicit onboarding.** Volt register creates/logs-in generic User without organization/role assignment. pending-approval route alone does not implement approval. Verify intended registration/product policy.
- **Confirmed: replacement creates a copy.** LeadReplacement::approve creates a replacement lead from original contact data and links request. This is not a newly sourced customer and can interact with duplicate-delivery rules.
- **Confirmed: parallel assignment branches.** Non-null assignment_config takes precedence over old project rules; capacity/equal_distribution/priority and terminal-stage handling differ. Avoid changing one branch assuming the other is obsolete.
- **Confirmed: parallel first-response tracking.** ExecutiveResponseService validates current assignee/activity timing. FirstResponseObserver also updates legacy first_response_at more broadly for older stage/activity paths. Older SLA/replacement calculations and new executive SLA may diverge.
- **Confirmed: separate Graph defaults.** Older Page/lead calls hardcode v19.0; MetaAdAccountService defaults v27.0. services.meta.graph_version config lookup does not itself establish env wiring. Upstream support was not checked online in this local review.
- **Confirmed: stale names/comments.** ReserveCreditOnLeadCreated and reserveForLead no longer mean an intake money reservation. Recharge URLs/amount field now represent lead-credit requests. Old comments mentioning consent may refer to conditional internal approval rather than an independent consent record.

## Static/demo data and legacy persistence

- ProcessInboundLeadJob supplies artificial budget (7,500,000), city (Mumbai), property type (2 BHK), placeholder email/name when missing. These can distort reporting/lead qualification and should be nullable/explicitly unknown if product rules permit.
- DatabaseSeeder includes demo organizations/users/leads/transactions/activities; many Deltek/demo/sample/repair seeders exist. Do not run them on production merely to repair access or add missing roles.
- clients/client_users were dropped; Client model is users alias. payments_received table exists historically while PaymentReceived model aliases payments; Payment also targets payments. Audit usage/data/retention before any removal.
- RoleAndPermissionSeeder delegates RoleSeeder; multiple role/repair seeders and migrations overlap. This is compatibility layering, not proof all can be removed.
- Existing legacy money history is intentionally preserved. Unreconciled zero active credits after integer migration is expected behavior, not evidence data was lost. down() intentionally blocks automatic rollback.
- Reconciliation UI only loads the latest 100 ledger entries into its selector and30 disputes into its list. Older records beyond those limits need search/pagination for complete historical management. The current Mark as Consumption operation is per-entry and explicit, not an automatic bulk conversion.

## Performance/concurrency risks needing measurement

1. Queue default retry_after 90s versus ProcessClientDeliveryRun timeout 120s can allow concurrent visibility before completion with multiple workers. Align reservation timeout/worker policy; business locks reduce but do not remove operational duplication risk.
2. Configured assignment locks organization and queries per candidate/day/load; old capacity selection queries per user. Large teams need query profiling and aggregate queries.
3. Lead project_id accessor queries campaign each access; credit wallet used/total appended accessors issue ledger aggregates. Serializing table collections can multiply queries. Measure eager-loading/aggregate opportunities before caching sensitive totals.
4. deliveryStatistics recursively queries campaign statistics. Rich organization/dashboard/report screens contain multiple aggregates and may scan large datasets; no load test was performed.
5. BulkLeadUpload keeps full rows in Livewire state and imports under one project transaction. Large files can increase response memory, lock time and Livewire payload size. Consider queued chunk processing after defining partial-failure semantics.
6. Polling Meta forms across all accounts runs as a scheduled command; max limit 100 per form does not prove historical completeness. Check cursor/backfill strategy, cron duration and API limits against real data.
7. Lead code generation reads max ID and probes codes before insert; lead_code unique protects integrity but concurrent creation may collide/retry needs verification on MySQL.
8. Archive protection is an Eloquent builder rule, not SQL row-level security. DB::table/raw reporting/deletion must apply archive/tenant constraints explicitly. Audit append-only behavior is also bypassable by direct DB writes.

## Verification history and limits

Earlier credit-system work in this chat passed114 selected tests plus build/Blade compilation; latest legacy consumption change passed15 tests/80 assertions. Those are historical targeted results, not a full-suite pass for this documentation snapshot. Two communication tests failed on default unread-notification expectations while implementation uses compatibility inbox behavior; do not silently suppress them. A calendar test had a UTC/application-timezone expectation mismatch. OrganizationManager broad tests previously hit demo seed recipient/campaign organization mismatch. Reproduce and classify these before using the full suite as a release gate.

This documentation task ran no tests, migrations, seeds, external API calls or browser audit. Live deployment status, actual token permissions, provider delivery, backup restore, complete role matrix, high-volume concurrency and mobile/theme correctness remain unverified.

## Recommended order

1. Contain the auto-offline command defect; authenticate/validate inbound endpoints and remove cross-tenant routing fallbacks.
2. Unify identity/scoping and organization suspension semantics; add adversarial role/API/export tests.
3. Stabilize migration/reconciliation and historical ledger management, including paginated legacy selection.
4. Align worker timeouts and test multi-worker delivery/charge/assignment idempotency on deployment DB engine.
5. Replace simulated transports or label them clearly; design Gemini only if required.
6. Consolidate duplicate lifecycle/assignment/version paths with characterization tests, then profile queries and large imports.
