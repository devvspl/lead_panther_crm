# Development guidelines for future agents

## Start with context, then verify the targeted code

1. Read AI_AGENT_CONTEXT and only the relevant detailed document.
2. Check git status/diff; preserve existing work. This documentation snapshot includes uncommitted/staged credit-reconciliation changes.
3. Follow AGENTS.md: prefer codebase-memory-mcp search_graph/trace_path/get_code_snippet/query_graph for discovery. Index if absent; current graph may lag recently added source. Fall back to targeted source search when results are incomplete or searching literals/config.
4. Read actual route → component/controller → service/model → observer/listener → migration. Do not infer implementation from file names or comments. Use the schema provenance index to avoid rescanning all migrations.
5. Keep these docs updated after structural changes; mark source versus deployment/runtime evidence explicitly.

## Core invariants

- client_id is a users ID. organization_id groups users. Leads derive project via campaign, not a physical project_id column.
- Prefer Lead::panelQuery for builder/executive/leader panels; explicitly scope background/raw DB queries. withoutGlobalScopes does not bypass custom archive filtering.
- Enforce permissions on every action/direct request, not just Blade conditionals. Locked Livewire IDs prevent tampering but do not substitute for authorization of selected records.
- Delivery, assignment and response SLA are separate settings. No lead delivery credit deduction before successful release.
- Preserve lead/wallet/plan locking and unique charge/item identities. Keep transactional changes and after-commit notification side effects in the established pipeline.
- Preserve integer credit validation, nonnegative balance and immutable original legacy history. Reconciliation/correction writes new evidence. Do not auto-convert dummy money or price historical consumption using today's override.
- Restored leads retain state and assignment hold unless Super Admin explicitly requests assignment. Do not trigger creation/reassignment observers during ordinary restore.
- Raw writes bypass observer/audit/archive protections; justify and explicitly enforce equivalent invariants.
- Never serialize secrets or lower-role source/financial data. Use presenters/redactors and scoped exports.

## Conventions and UI reuse

PSR-4 App namespace, Eloquent relationships/casts, Livewire classes and paired Blade, Laravel validation/ValidationException, DB::transaction (many workflows retry3), scoped model refreshes/locks, toast/modal events. Follow adjacent component style; code currently mixes formatting and concise trait methods, so avoid broad unrelated reformatting.

Reuse layouts.app/guest, ui.themed-select, advanced-table/HasAdvancedTable, modal/offcanvas, date-range-picker and existing semantic theme tokens/icons. Stable wire:key for dynamic repeated controls; typed integer bound properties may truncate input before validation, so preserve existing quantity validation strategy. Ensure filters reset pagination and retain personal table preferences.

Use ExportService/BaseStyledExport and explicit column whitelists. Pagination and hidden columns are not access control. Source/revenue visibility requires component and serialized output protection.

## Local setup and verification (instructions, not executed by this audit)

Use compatible PHP8.2+, Composer, project lockfiles and Node supported by locked Vite. Typical setup: composer install, npm ci, app key/config and local database provisioning; inspect composer scripts first because composer setup explicitly runs migrations. Do not point setup commands at a live DB. On this Windows workspace use npm.cmd to avoid PowerShell execution-policy ambiguity.

Relevant commands after a code change, in a safe local/test environment:

```text
php -l path/to/changed.php
php vendor/phpunit/phpunit/phpunit tests/Feature/<target>
npm.cmd run build
php artisan view:cache
git diff --check
```

phpunit.xml sets APP_ENV=testing, DB_CONNECTION=sqlite, DB_DATABASE=:memory:, empty DB_URL, array cache/session/mail and sync queue. A cached Laravel config can defeat intended settings: verify configuration isolation before running tests, clearing local config only when authorized. Never migrate/seed the active developer or production database to satisfy tests. Do not run view:cache concurrently with PHPUnit rendering tests; shared compiled views can race.

Critical regression targets: Credits/CreditReservationTest and RechargeApprovalTest, Leads/ClientDeliveryTest and LeadDeliveryScheduleTest, Team/LeadSourceVisibilityTest, Admin/OrganizationOverviewMetricsTest and OrganizationProjectSettingsTest, Reports/ReportingDashboardTest, Replacement/LeadReplacementTest, Settings/MetaIntegrationTest. See KNOWN_ISSUES before claiming full-suite success.

For permissions test active/inactive Super Admin, builder/client role variants, team leader, executive, unrelated org, direct HTTP/Livewire/API/export and guessed IDs. For accounting test pending/failure/duplicate/retry/rollback, unreconciled wallet, insufficient balance, refund and legacy conversion. SQLite sync tests do not validate MySQL row locks or multiple workers.

## Deployment and high-risk files

Review migrations on backup copy of actual engine/data. Do not edit historical applied migrations casually. Credit schema down is intentionally disabled. Pause old writers during rename migration; deploy assets/code together, migrate using approved release procedure, clear appropriate caches, restart workers and reconcile accounts before delivery resumes. See docs/lead-credits-migration.md.

Special-care files: Lead.php, CreditTransaction.php, LeadCreditService.php, LeadDistributionService.php and its three traits, ClientScope.php, ArchiveAwareBuilder.php, User.php, AppServiceProvider.php, routes/console.php, ProcessInboundLeadJob.php, MetaLeadSyncService.php, MetaAdAccountService.php, GitSyncService.php, OrganizationDetail/LeadDetail/LeadKanban and BaseStyledExport.

Never run DatabaseSeeder/demo seeders, reset/cleanup commands, Git hard reset/push/deploy, backup restore or scheduler jobs during code discovery. Never print .env/credentials, tokens, password-reset links, push private keys or raw production logs. Environment variable names alone are safe documentation.
