# Project structure and entry points

## Source map

| Path | Responsibility |
|---|---|
| `bootstrap/app.php`, `bootstrap/providers.php` | Route wiring, middleware aliases/priority, registered providers |
| `routes/web.php`, `api.php`, `auth.php`, `channels.php`, `console.php` | Web/API/auth/broadcast/scheduled entry points |
| `app/Livewire/Admin/` | Organizations/detail, users, credits, archives, audit, jobs, backups, Git |
| `app/Livewire/Leads/` | Kanban/table, detail drawer, upload/history, client delivery |
| `app/Livewire/Settings/` | IntegrationsManager, MetaAdAccounts, organization/users, assignment configuration |
| `app/Livewire/Team/`, `Distribution/`, `Activity/`, `Replacement/`, `Reports/`, `Credits/` | Domain screens and user actions |
| `app/Livewire/Concerns/` | Shared advanced-table and screen behavior |
| `app/Http/Controllers/` | Dashboard, auth verification, documents, theme, notifications, push, archive HTTP actions |
| `app/Http/Controllers/Api/Webhooks/` | Inbound Meta, Google, portal, owned and manual capture |
| `app/Http/Resources/LeadResource.php` | Delegates API presentation to LeadPresenter |
| `app/Models/`, `Models/Scopes/`, `Models/Builders/` | Eloquent models, tenant scope, archive-aware and append-only builders |
| `app/Services/` | Distribution, pricing/credits, archive, Meta, notifications, Git, backups/tracked operations |
| `app/Services/Concerns/` | ProcessesClientDelivery, ConfiguresTeamAssignment, ProcessesInternalLeads |
| `app/Jobs/`, `app/Console/Commands/` | Background and command entry points |
| `app/Events/`, `Listeners/`, `Observers/` | Cross-cutting workflow hooks; some older listeners are empty scaffolds |
| `app/Support/` | Navigation, team access, theme, redaction, presenters, source mappers |
| `app/Traits/`, `Rules/`, `Contracts/`, `Exports/`, `Mail/`, `Notifications/` | Shared persistence rules, validation, transports and output |
| `database/migrations/`, `seeders/`, `factories/` | Ordered schema evolution and access/demo/test data |
| `resources/views/layouts/`, `components/ui/`, `livewire/` | Shells, reusable components, screens and Volt auth views |
| `resources/js/`, `resources/css/` | Build assets, Echo, Flatpickr and CSS |
| `public/` | Web entry, service-worker/browser-push assets, generated build |
| `storage/app/private/` | Documents, backups and encrypted push-key fallback |
| `storage/logs/`, `storage/framework/` | Runtime logs, views/cache; not authoritative source |
| `tests/Feature/`, `tests/Unit/` | Workflow, permission and UI regressions |
| `docs/`, `README.md`, `DEVELOPER_GUIDE.md` | Existing docs; verify older statements against current code |

## Route map

| Route | Entry / boundary |
|---|---|
| `/dashboard` | Role-specific dashboard view via NavigationConfig; auth + verified |
| `/admin/organizations` | OrganizationManager |
| `/admin/organizations/{organization}` | OrganizationDetail; tab embeds domain components |
| `/leads`, `/leads/kanban` | LeadKanban; component and model access checks |
| `/leads/upload`, `/leads/upload-history` | BulkLeadUpload / UploadHistory |
| `/followups` | Activity/FollowupList |
| `/reports` | ReportsContainer; nested report authorization |
| `/credits`, `/admin/credits` | Client versus Super Admin credit UI |
| `/admin/recharge-requests` | RechargeApprovalQueue; old URL now means lead-credit requests |
| `/replacements`, `/client/replacements` | Admin review / client history |
| `/builder/team`, `/team-leader/team` | BuilderTeamManager with different guards |
| `/partner/team` | PartnerTeamManager |
| `/settings/integrations` | IntegrationsManager; Super Admin; nested MetaAdAccounts |
| `/admin/archived-leads` | ArchivedLeads; archive/restore/purge HTTP controller routes also exist |
| `/notifications`, `/notifications/push/*` | Inbox/open actions, push config/subscription/test endpoints |
| `/admin/git-sync`, `/admin/backups`, `/admin/failed-jobs` | Super Admin operations |
| `/documents/download/{path}` | Auth plus controller signature/ownership checks |
| `/proposals/view/{proposal}` | Signed public proposal viewer |
| `/api/webhooks/*`, `/api/leads/*` | See API_INTEGRATIONS security caveats |

Builder, broker, partner, sales, client and team-leader dashboard prefixes remain. Public landing/docs/legal pages and Volt auth routes are separate. No `/artisan-schedule-run` HTTP route exists in the reviewed routes.

## Coding patterns

Livewire components combine queries, validation and mutations; Blade is not the whole implementation. Actions normally authorize a fresh scoped record, validate, transact, then dispatch `toast`, `open-modal`, `close-modal` or refresh events. Many page components render `->layout('layouts.app')`. Volt auth pages live under `resources/views/livewire/pages/auth/` and use `layouts.guest`; do not assume `components.layouts.app` exists.

`AppServiceProvider` binds log gateways, installs Super Admin gate bypass, observes audited models, installs lead/first-response/scoring/notification observers, and subscribes auth audits. Typed event listener discovery also matters. This checkout has no separate `EventServiceProvider.php` or populated central policy directory to replace action/query guards.

High-care components: OrganizationDetail, LeadKanban, LeadDetail, DashboardShell, IntegrationsManager. High-care persistence code: Lead, CreditTransaction, ArchiveAwareBuilder, ClientScope, User role normalization. Source graph is useful for navigation but was stale for recently added classes; verify source when graph results are incomplete.
