# AI agent context: read this first

> Reviewed 21 November 2026 (Asia/Calcutta), local HEAD 52bc995 plus existing working-tree credit/reconciliation changes. This is source understanding, not a production deployment attestation. Documentation-only task; application/config/database unchanged.

## Architecture in one minute

Laravel12.66/PHP8.2+, Livewire3.8/Volt, Blade/Alpine/Tailwind/Vite monolith. Eloquent persistence, service/trait workflows, observers and discovered typed listeners. Spatie roles; Laravel scheduler/database-default queue; Excel; Meta Graph; email/Web Push/Reverb. Most behavior is in Livewire actions, not REST controllers. WhatsApp/SMS are mock logging transports; no Gemini implementation found in app/config.

## Non-negotiable data facts

- Organization is tenant group. **Client extends User and uses users table**. client_id means billing user ID, never organization ID. Original clients/client_users were migrated/dropped.
- Lead belongs to Campaign→Project; physical leads.project_id was removed. Accessor/setter supplies old API compatibility.
- Team ownership is polymorphic Organization/User; membership, leader and active user state all affect eligibility.
- Lead::panelQuery scopes builder to owner/org, executive to self, leader through TeamAccess. Older ClientScope remains and has ID-domain risks.
- ArchiveAwareBuilder excludes archived leads/dependents even with withoutGlobalScopes; raw SQL bypasses it. Restore sets assignment hold unless explicit reassignment.
- Lead credits are integers. Old money is legacy_* history, not spendable; old wallets require reconciliation. Default cost1; overrides project > supplied team > org > global. Keep actual ledger consumption, not current-price estimates.
- Remaining=wallet balance; Used=nonlegacy lead charges minus lead refunds; Total=Remaining+Used. Delivery success and charge commit together; unique delivery_charge_lead_id plus locks protect retries. No charge for pending/failed/duplicate delivery.
- Internal processing, client delivery interval, executive assignment quotas and executive response SLA are separate configurations. Internal approval is required only when organization.internal_processing_enabled is true.

## Main navigation map

| Change | Start reading |
|---|---|
| HTTP/access | routes/web.php, api.php, auth.php; bootstrap/app.php |
| Client directory/details | app/Livewire/Admin/OrganizationManager.php, OrganizationDetail.php and paired views |
| Lead lists/actions/detail | app/Livewire/Leads/LeadKanban.php, LeadDetail.php; app/Models/Lead.php |
| Capture/import | BulkLeadUpload.php; app/Jobs/ProcessInboundLeadJob.php; Support/LeadMappers |
| Delivery/assignment | app/Services/LeadDistributionService.php; Services/Concerns/ProcessesClientDelivery.php, ConfiguresTeamAssignment.php, ProcessesInternalLeads.php |
| Credit mutation/pricing | Services/LeadCreditService.php, LeadPricingService.php; Models/CreditTransaction.php, CreditWallet.php |
| Reconciliation/disputes | Livewire/Credits/Reconciliation.php and view; docs/lead-credits-migration.md |
| Access/roles | Models/User.php, Models/Scopes/ClientScope.php; Support/TeamAccess.php, NavigationConfig.php; RoleSeeder/TeamLeaderRoleSeeder |
| SLA | Services/ExecutiveResponseService.php, Observers/FirstResponseObserver.php, Models/LeadAssignment.php |
| Notifications | Services/NotificationDeliveryService.php, BrowserPushService.php, EmailDesignService.php; Console/Commands/CheckWebNotificationAlerts.php |
| Meta | Livewire/Settings/IntegrationsManager.php, MetaAdAccounts.php; Services/MetaLeadSyncService.php, MetaFormSyncService.php, MetaAdAccountService.php |
| Archive | Services/LeadArchiveService.php; Models/Builders/ArchiveAwareBuilder.php |
| Schedule/ops | routes/console.php; Services/ScheduledTaskService.php, GitSyncService.php; Jobs and Console/Commands |
| Audits | Models/AuditLog.php, Observers/AuditLogObserver.php, Support/AuditContext.php, AuditRedactor.php; AppServiceProvider |
| Reports/Excel | Livewire/Reports; Exports/BaseStyledExport.php; Services/ExportService.php |

## Traced lifecycle

Intake→LeadObserver schedules hold/date/plan. Internal enabled→platform calling/verification→eligible handover request. Automatic plan cycle enrolls eligible leads; no manual queue enrollment needed. ProcessClientDeliveryRun locks/rechecks mapping/status/duplicates/approval and calls releaseDelivery. Release writes delivered visibility+integer charge+downstream assignment transactionally. assignLeadToUser closes prior assignment/history, adds new LeadAssignment. Its creation starts current executive deadline (default 15 minutes). First qualifying current-assignee response records SLA; operational minute task emits deduplicated approaching/breached alerts. Followups are separate scheduled reminders.

## Permission shorthand

Active Super Admin: global administration, archive/restore/purge, credit adjustments/reconciliation/disputes, delivery configuration, integrations/Git/audits, Lead Journey/source/money. Builder: own-org delivered leads/team actions. Team Leader: permission-bound managed team scope and route allowlist. Executive: assigned leads and permitted stage operations; no assignment/distribution or privileged credits/archive. “Client Admin” is not a reliable separate canonical role: old client alias may normalize to Executive. Test actual records and direct actions.

## Reusable UI and coding practices

layouts.app/guest; ui.themed-select, advanced-table+HasAdvancedTable, modal/confirmation-modal/offcanvas, date-range-picker, dropdown/button/card/toast and existing action renderers. ThemeService uses per-user general_settings. Use semantic theme colors/icons, stable wire:key and named open-modal/close-modal/toast events. Full-page components must select an existing layout.

Use validation, fresh scoped models, transactions/row locks and after-commit external notifications. Model observers add side effects: inspect AppServiceProvider and typed listeners before duplicating writes. Raw DB updates need explicit scoping/audit/archive protection. Presenters/exports must hide lower-role lead sources and property/payment money. Never expose decrypted credentials or copy .env/log contents into docs.

## Known hazards before changes

1. Webhook POST auth is placeholder; public /api/leads/manual lacks explicit auth/validation/throttle. Fix security contract first.
2. Older tenant scope/document checks compare user/client IDs and org IDs; unmapped intake falls back to first client/project and name-only campaign.
3. **Auto-offline defect:** schedule calls team:auto-offline, actual signature teams:auto-offline; implementation inactivates all active executives without inactivity filter. Do not just correct spelling/run it.
4. Queue retry_after 90s versus delivery timeout 120s; verify concurrency on deployment engine.
5. Parallel old/new assignment and response tracking; replacement copies original contact; older Page Graph v19 versus ads default v27.
6. Legacy wallets need reconciliation; selector currently latest 100 ledger rows / 30 disputes. Explicit historical Mark as Consumption deducts once from verified allocation; avoid double-counting already-net balances.
7. Empty listener/model scaffolds and demo seeders exist. Do not infer active functionality or delete tables from names/caller counts.

## Safe work and validation

Check status/diff first; preserve existing changes. Follow AGENTS codebase-memory discovery preference; graph may lag new source. Read relevant detailed doc and targeted source, not the whole repo repeatedly. Use php -l, focused PHPUnit, build and Blade checks for code changes; no tests were rerun for this documentation-only audit. phpunit.xml uses in-memory SQLite/sync queue; verify cached config cannot redirect it. Do not run view:cache concurrently with rendering tests. Never run demo/reset/seed/Git deployment or live scheduler as discovery. Existing tests have known failures noted in KNOWN_ISSUES; prior selected passes are not full-suite certification.

## Detailed references

[Architecture](PROJECT_OVERVIEW.md) · [Source map](PROJECT_STRUCTURE.md) · [Schema](DATABASE_SCHEMA.md) · [Roles](USER_ROLES_PERMISSIONS.md) · [Workflows](BUSINESS_WORKFLOWS.md) · [Distribution](LEAD_DISTRIBUTION_FLOW.md) · [Scheduler](CRON_QUEUE_SYSTEM.md) · [Integrations](API_INTEGRATIONS.md) · [UI](FRONTEND_COMPONENTS.md) · [Issues](KNOWN_ISSUES.md) · [Guidelines](DEVELOPMENT_GUIDELINES.md).

## Meta fetch follow-up (21 November 2026)

Manual Page form fetch and scheduled discovery now share MetaFormSyncService. Cursor pagination follows paging.next even for short/empty pages; cached rows are not reported as fresh API results. Audit actions meta.forms.completed/partial/failed expose safe per-Page counts/errors in settings. Campaign/ad-set sync remains separate under Connected Ad Accounts, with per-edge counts/history and an explicit Sync Campaigns & Ad Sets action. Saved mappings are preserved and fetching forms does not invent CRM campaigns. MetaLeadSyncService uses the configured Graph version; older webhook/connection-test paths still need version review.
