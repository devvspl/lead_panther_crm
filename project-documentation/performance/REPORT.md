# Sidebar navigation and database performance audit

Date: 26 November 2026 (Asia/Calcutta). Scope: current local working tree; no production changes.

## Evidence and limits

- The configured MySQL endpoint is loopback. A read-only connection attempt failed with PDO error 2002 before metadata could be read. Actual deployed indexes, MySQL EXPLAIN, production cardinality, network latency and browser click-to-usable times are **unverified**. No index was added or removed.
- Before/after measurements below are real **in-process HTTP kernel + Blade/Livewire render** measurements using disposable SQLite `:memory:` fixtures. They are not browser or production measurements. Each page ran three times; tables use the median of all three runs. First requests include compilation/container warm-up. OS load varied; do not interpret latency changes as causal production improvements.
- Fixed workload: 1,000 synthetic leads; 24 users in one organization; one CRM project/campaign; four connected Meta accounts. All four principal roles were profiled. Team Leader has permissions but no managed-team fixture, so that role measures the empty authorized scope, not a busy team. Other tenant combinations and large ledgers remain staging work.
- Auth, account-active, role, Team Leader, tenant/archive/visibility restrictions and request-audit middleware remain enabled. HTTP external requests are prevented, queues and model events are faked in the fixture harness. Session/cache use the test configuration. Row writes in the fixture are not measurements of production intake workflows.
- Controller-only time is not isolated: Livewire full-page components perform most work in mount/render/computed getters. `response_ms` includes the full backend render, with no browser, CDN downloads, JS execution, first paint or interactivity. Query time is Laravel QueryExecuted elapsed time; scanned-row counts are unavailable on SQLite.
- Raw evidence: [before.json](before.json), [after.json](after.json). SQL text contains placeholders, not private binding values. SQLite `EXPLAIN QUERY PLAN` is recorded for slow query shapes using NULL binds; it shows index/scan shape, not actual tenant selectivity or scanned row counts. Never substitute these plans for MySQL plans.

## Sidebar and loading findings

- `resources/views/components/ui/sidebar.blade.php` already uses `wire:navigate.hover`; both menu sections share existing NavigationConfig permission filtering. No full-page-navigation replacement was needed. Hover starts backend page prefetch before a click and can do discarded work. No measured browser trace proves duplicate AJAX, so prefetch behavior was preserved.
- All sidebar destinations returned HTTP 200 in the fixture. Git Sync, Backups/Health and Dev Tools were enumerated but deliberately not executed: their mount/render paths inspect Git/filesystem/migration/operational state. They require a controlled staging browser session. They are not included in the ranking.
- Projects and campaigns have no separate top-level sidebar item in the current NavigationConfig. They are managed through client details/settings/mapping views; source reviewed, no separate initial-navigation timing claimed.
- ReportsContainer renders its selected report, not every report tab. Lead table mode is paginated and Kanban stage lists are capped; these existing patterns were retained. Follow-up personal lists and Integration dropdown/catalog collections still load full matching collections; actual cardinalities and UX requirements must be assessed before changing these.
- Shared layout loads remote Google Fonts (five font families), Chart.js and Flatpickr on every page. These are potential external blocking/transfer costs. No local CDN/browser timing evidence was available; no risky script loading/lifecycle redesign was made.
- Operational Git/backup pages and every lower-role variant were reviewed via NavigationConfig/routes/source; broker/channel-partner/client aliases were not separately timed. User identity of the live browser was not supplied.

## Verified changes

1. LeadKanban previously counted each of 19 stages and fetched empty stage lists. A single grouped count over the **same scoped base query** now supplies stage totals; empty stages return empty collections without querying rows. Stage aliases, sorting, limits, records and visibility rules remain unchanged.
2. Builder dashboard team performance ran six counts for every active organization user (144 counts with 24 active users). It now groups the same `Lead::panelQuery()` by assignee once and returns zero totals for members without visible leads. No cross-tenant cache or global query was introduced.
3. Meta account table accessed sync_counts four times per account. Each access issued grouped record counts and a Page count: 32 queries for four accounts. The list now uses existing Eloquent `withCount` relationships, and the accessor consumes prefetched counts. Its non-prefetched fallback is retained for other callers.
4. Layout and theme modal each read the same theme settings. Layout passes its existing theme array into the existing anonymous component, saving one query per page without introducing cache invalidation or cross-user cache risks.

## Measured before / after

All times in milliseconds. Backend is server response/render time, not browser usability time. DB, query and duplicate counts are medians of three runs; SQL/binding identity is hashed for exact duplicate counting.

| Role | Sidebar page | Backend before | Backend after | Queries before → after | DB time before → after | Exact duplicates before → after |
|---|---|---:|---:|---:|---:|---:|
| super-admin | `/admin/dashboard` | 484.07 | 584.34 | 211 → 210 | 81.11 → 90.11 | 17 → 16 |
| super-admin | `/leads` | 773.33 | 1057.02 | 170 → 133 | 87.48 → 92.36 | 36 → 35 |
| super-admin | `/followups` | 135.79 | 146.07 | 26 → 25 | 2.91 → 4.03 | 2 → 1 |
| super-admin | `/leads/upload` | 64.93 | 72.30 | 13 → 12 | 1.71 → 2.18 | 1 → 0 |
| super-admin | `/distribution/availability` | 122.62 | 110.81 | 16 → 15 | 2.23 → 2.89 | 2 → 1 |
| super-admin | `/credits` | 92.31 | 96.70 | 28 → 27 | 3.14 → 3.97 | 7 → 6 |
| super-admin | `/replacements` | 96.53 | 127.13 | 19 → 18 | 2.42 → 2.95 | 2 → 1 |
| super-admin | `/reports` | 62.10 | 97.78 | 12 → 11 | 3.71 → 4.62 | 1 → 0 |
| super-admin | `/settings/integrations` | 147.14 | 182.44 | 86 → 53 | 9.40 → 10.24 | 42 → 17 |
| super-admin | `/admin/organizations` | 96.01 | 165.70 | 23 → 22 | 2.69 → 3.80 | 4 → 3 |
| super-admin | `/admin/users` | 127.39 | 209.39 | 22 → 21 | 2.62 → 4.00 | 3 → 2 |
| super-admin | `/admin/audit-logs` | 110.43 | 251.22 | 19 → 18 | 1.94 → 5.93 | 2 → 1 |
| super-admin | `/notifications` | 87.19 | 123.66 | 14 → 13 | 2.18 → 2.43 | 1 → 0 |
| super-admin | `/admin/failed-jobs` | 44.72 | 77.05 | 12 → 11 | 1.42 → 1.87 | 1 → 0 |
| super-admin | `/admin/recharge-requests` | 75.07 | 93.41 | 13 → 12 | 1.88 → 2.52 | 2 → 1 |
| super-admin | `/admin/archived-leads` | 128.22 | 174.47 | 14 → 13 | 3.11 → 2.96 | 1 → 0 |
| builder | `/builder/dashboard` | 460.38 | 573.30 | 201 → 57 | 79.32 → 72.82 | 6 → 5 |
| builder | `/leads` | 263.52 | 380.64 | 107 → 76 | 37.19 → 28.06 | 6 → 2 |
| builder | `/followups` | 66.61 | 134.31 | 20 → 19 | 7.11 → 17.89 | 2 → 1 |
| builder | `/builder/team` | 308.34 | 569.25 | 32 → 31 | 15.10 → 29.42 | 9 → 8 |
| builder | `/client/replacements` | 57.91 | 82.67 | 8 → 7 | 1.33 → 2.56 | 1 → 0 |
| builder | `/reports` | 207.32 | 361.91 | 9 → 8 | 16.64 → 33.14 | 2 → 1 |
| team-leader | `/team-leader/dashboard` | 137.17 | 176.19 | 27 → 26 | 19.53 → 26.50 | 9 → 8 |
| team-leader | `/leads` | 321.68 | 243.15 | 106 → 74 | 74.77 → 28.71 | 14 → 10 |
| team-leader | `/team-leader/team` | 211.24 | 162.67 | 32 → 31 | 11.07 → 9.74 | 12 → 11 |
| team-leader | `/followups` | 142.10 | 125.60 | 24 → 23 | 22.52 → 18.92 | 5 → 4 |
| team-leader | `/reports` | 116.53 | 68.72 | 11 → 10 | 7.35 → 6.29 | 5 → 4 |
| sales-executive | `/sales/dashboard` | 156.64 | 69.57 | 21 → 20 | 12.38 → 7.15 | 2 → 1 |
| sales-executive | `/leads` | 403.08 | 350.75 | 88 → 57 | 46.09 → 18.34 | 6 → 2 |
| sales-executive | `/followups` | 105.90 | 105.26 | 20 → 19 | 5.50 → 7.46 | 2 → 1 |
| sales-executive | `/reports` | 325.77 | 302.49 | 9 → 8 | 23.79 → 26.96 | 2 → 1 |

Latency increased for several pages despite fewer queries; host variance/rendering cost means a wall-clock speedup has **not** been established. The deterministic reduction in query work is verified.

### Slowest measured pages

Before: super-admin leads 773.33 ms; super-admin dashboard 484.07 ms; builder dashboard 460.38 ms; sales-executive leads 403.08 ms; sales-executive reports 325.77 ms.
After: super-admin leads 1057.02 ms; super-admin dashboard 584.34 ms; builder dashboard 573.30 ms; builder team 569.25 ms; builder leads 380.64 ms.

### Slowest observed queries for each measured page

The table reports the single slowest query event among the three runs. Individual sub-millisecond timings are noisy. Complete SQL and each run appear in the raw JSON. No MySQL scanned-row values were available.

| Role / page | Before ms / query | After ms / query |
|---|---|---|
| super-admin / dashboard | 2.81 / `select COUNT(*) as total_leads, COUNT(CASE WHEN DATE(created_at) = '2026-11-26' THEN 1 END) as leads_today, COUNT(CASE WHEN status = 'new' OR current_stage = 'new' THEN 1…` | 3.49 / `select COUNT(*) as total_leads, COUNT(CASE WHEN DATE(created_at) = '2026-11-26' THEN 1 END) as leads_today, COUNT(CASE WHEN status = 'new' OR current_stage = 'new' THEN 1…` |
| super-admin / leads | 14.85 / `select count(*) as aggregate from "leads" where "internal_processing_pending" = ? and "created_at" between ? and ? and "current_stage" != ? and "leads"."archived_at" is n…` | 15.60 / `select * from "leads" where "internal_processing_pending" = ? and "created_at" between ? and ? and "leads"."archived_at" is null` |
| super-admin / followups | 0.54 / `select "id", "name", "email" from "users" where (exists (select * from "roles" inner join "model_has_roles" on "roles"."id" = "model_has_roles"."role_id" where "users"."i…` | 0.91 / `select * from "users" where "status" = ? and exists (select * from "roles" inner join "model_has_roles" on "roles"."id" = "model_has_roles"."role_id" where "users"."id" =…` |
| super-admin / bulk-import | 0.35 / `select "id", "name", "email" from "users" where (exists (select * from "roles" inner join "model_has_roles" on "roles"."id" = "model_has_roles"."role_id" where "users"."i…` | 0.45 / `select "id", "name", "email" from "users" where (exists (select * from "roles" inner join "model_has_roles" on "roles"."id" = "model_has_roles"."role_id" where "users"."i…` |
| super-admin / distribution | 0.37 / `select "id", "name", "email" from "users" where (exists (select * from "roles" inner join "model_has_roles" on "roles"."id" = "model_has_roles"."role_id" where "users"."i…` | 0.91 / `select * from "users" where exists (select * from "roles" inner join "model_has_roles" on "roles"."id" = "model_has_roles"."role_id" where "users"."id" = "model_has_roles…` |
| super-admin / credits | 0.45 / `select sum("credit_used") as aggregate from "credit_transactions" where "client_id" = ? and "is_legacy" = ? and "lead_id" is not null and "transaction_type" in (?, ?, ?)` | 0.39 / `select "id", "name", "email" from "users" where (exists (select * from "roles" inner join "model_has_roles" on "roles"."id" = "model_has_roles"."role_id" where "users"."i…` |
| super-admin / replacements | 0.34 / `select "id", "name", "email" from "users" where (exists (select * from "roles" inner join "model_has_roles" on "roles"."id" = "model_has_roles"."role_id" where "users"."i…` | 0.39 / `select "id", "name", "email" from "users" where (exists (select * from "roles" inner join "model_has_roles" on "roles"."id" = "model_has_roles"."role_id" where "users"."i…` |
| super-admin / reports | 2.56 / `select "lead_source_id", count(*) as total_leads, SUM(COALESCE((SELECT SUM(CASE WHEN transaction_type = 'refund' THEN -credit_used ELSE credit_used END) FROM credit_trans…` | 2.48 / `select "lead_source_id", count(*) as total_leads, SUM(COALESCE((SELECT SUM(CASE WHEN transaction_type = 'refund' THEN -credit_used ELSE credit_used END) FROM credit_trans…` |
| super-admin / integrations | 0.64 / `select count(*) as aggregate from "portal_accounts" inner join "meta_ad_account_pages" on "portal_accounts"."id" = "meta_ad_account_pages"."portal_account_id" where "meta…` | 1.93 / `select "name", "id" from "portal_accounts" where "type" = ? order by "name" asc` |
| super-admin / organizations | 0.67 / `select "organizations".* from (select "organizations".*, (select count(*) from "users" where "organizations"."id" = "users"."organization_id") as "users_count", (select c…` | 1.00 / `select "organizations".* from (select "organizations".*, (select count(*) from "users" where "organizations"."id" = "users"."organization_id") as "users_count", (select c…` |
| super-admin / users | 0.36 / `select "id", "name", "email" from "users" where (exists (select * from "roles" inner join "model_has_roles" on "roles"."id" = "model_has_roles"."role_id" where "users"."i…` | 0.76 / `select "roles".*, "model_has_roles"."model_id" as "pivot_model_id", "model_has_roles"."role_id" as "pivot_role_id", "model_has_roles"."model_type" as "pivot_model_type" f…` |
| super-admin / audit-logs | 0.33 / `select "id", "name", "email" from "users" where (exists (select * from "roles" inner join "model_has_roles" on "roles"."id" = "model_has_roles"."role_id" where "users"."i…` | 2.12 / `select count(*) as aggregate from "notifications" where "notifications"."notifiable_type" = ? and "notifications"."notifiable_id" = ? and "notifications"."notifiable_id" …` |
| super-admin / notifications | 0.41 / `select count(*) as aggregate from "notifications" where "notifications"."notifiable_type" = ? and "notifications"."notifiable_id" = ? and "notifications"."notifiable_id" …` | 0.86 / `select "id", "name", "email" from "users" where (exists (select * from "roles" inner join "model_has_roles" on "roles"."id" = "model_has_roles"."role_id" where "users"."i…` |
| super-admin / failed-jobs | 0.34 / `select "value", "key" from "general_settings" where "user_id" = ? and "key" like ?` | 0.53 / `select "id", "name", "email" from "users" where "status" = ? order by "name" asc` |
| super-admin / recharge-queue | 0.53 / `select "id", "name", "email" from "users" where (exists (select * from "roles" inner join "model_has_roles" on "roles"."id" = "model_has_roles"."role_id" where "users"."i…` | 0.72 / `select "id", "name", "email" from "users" where (exists (select * from "roles" inner join "model_has_roles" on "roles"."id" = "model_has_roles"."role_id" where "users"."i…` |
| super-admin / archived-leads | 2.04 / `select "id", "name" from "projects" order by "name" asc` | 1.20 / `select "id", "name", "email" from "users" where (exists (select * from "roles" inner join "model_has_roles" on "roles"."id" = "model_has_roles"."role_id" where "users"."i…` |
| builder / dashboard | 2.79 / `select count(*) as aggregate from "leads" where "internal_processing_pending" = ? and "delivery_pending" = ? and ("client_id" = ? or "assigned_to" = ? or exists (select *…` | 9.78 / `select * from "followups" where "lead_id" in (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?…` |
| builder / leads | 2.07 / `select count(*) as aggregate from "followups" where "lead_id" in (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,…` | 3.02 / `select count(*) as aggregate from "followups" where "lead_id" in (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,…` |
| builder / followups | 0.84 / `select count(*) as aggregate from "followups" where "lead_id" in (select "leads"."id" from "leads" where "internal_processing_pending" = ? and "delivery_pending" = ? and …` | 2.40 / `select * from "followups" where "lead_id" in (select "leads"."id" from "leads" where "internal_processing_pending" = ? and "delivery_pending" = ? and ("client_id" = ? or …` |
| builder / team | 12.57 / `select "users".*, (select count(*) from "leads" where "users"."id" = "leads"."assigned_to" and "leads"."internal_processing_pending" = ? and "leads"."delivery_pending" = …` | 22.10 / `select "users".*, (select count(*) from "leads" where "users"."id" = "leads"."assigned_to" and "leads"."internal_processing_pending" = ? and "leads"."delivery_pending" = …` |
| builder / replacements | 0.51 / `select * from "notifications" where "notifications"."notifiable_type" = ? and "notifications"."notifiable_id" = ? and "notifications"."notifiable_id" is not null and "org…` | 0.80 / `select count(*) as aggregate from "notifications" where "notifications"."notifiable_type" = ? and "notifications"."notifiable_id" = ? and "notifications"."notifiable_id" …` |
| builder / reports | 10.69 / `select * from "leads" where "internal_processing_pending" = ? and "delivery_pending" = ? and ("client_id" = ? or "assigned_to" = ? or exists (select * from "users" where …` | 22.05 / `select * from "leads" where "internal_processing_pending" = ? and "delivery_pending" = ? and ("client_id" = ? or "assigned_to" = ? or exists (select * from "users" where …` |
| team-leader / dashboard | 1.92 / `select * from "leads" where "internal_processing_pending" = ? and "delivery_pending" = ? and exists (select * from "users" where "leads"."client_id" = "users"."id" and "o…` | 3.36 / `select * from "leads" where "internal_processing_pending" = ? and "delivery_pending" = ? and exists (select * from "users" where "leads"."client_id" = "users"."id" and "o…` |
| team-leader / leads | 1.91 / `select "id" from "leads" where "internal_processing_pending" = ? and "delivery_pending" = ? and exists (select * from "users" where "leads"."client_id" = "users"."id" and…` | 2.73 / `select * from "projects" where "projects"."id" in (select "project_id" from "campaigns" where "id" in (select "campaign_id" from "leads" where "internal_processing_pendin…` |
| team-leader / team | 1.94 / `select count(*) as aggregate from "notifications" where "notifications"."notifiable_type" = ? and "notifications"."notifiable_id" = ? and "notifications"."notifiable_id" …` | 1.80 / `select count(*) as aggregate from "notifications" where "notifications"."notifiable_type" = ? and "notifications"."notifiable_id" = ? and "notifications"."notifiable_id" …` |
| team-leader / followups | 1.81 / `select count(*) as aggregate from "followups" where "lead_id" in (select "leads"."id" from "leads" where "internal_processing_pending" = ? and "delivery_pending" = ? and …` | 2.03 / `select * from "followups" where "lead_id" in (select "leads"."id" from "leads" where "internal_processing_pending" = ? and "delivery_pending" = ? and exists (select * fro…` |
| team-leader / reports | 1.74 / `select count(*) as aggregate from "notifications" where "notifications"."notifiable_type" = ? and "notifications"."notifiable_id" = ? and "notifications"."notifiable_id" …` | 1.43 / `select count(*) as aggregate from "notifications" where "notifications"."notifiable_type" = ? and "notifications"."notifiable_id" = ? and "notifications"."notifiable_id" …` |
| sales-executive / dashboard | 4.43 / `select count(*) as aggregate from "leads" where "assigned_to" = ? and "leads"."archived_at" is null` | 1.11 / `select count(*) as aggregate from "leads" where "assigned_to" = ? and strftime('%Y-%m-%d', "created_at") = cast(? as text) and "leads"."internal_processing_pending" = ? a…` |
| sales-executive / leads | 2.16 / `select count(*) as aggregate from "followups" where "lead_id" in (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,…` | 2.66 / `select count(*) as aggregate from "followups" where "lead_id" in (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,…` |
| sales-executive / followups | 0.73 / `select count(*) as aggregate from "followups" where ("assigned_to" = ? or ("assigned_to" is null and "lead_id" in (select "leads"."id" from "leads" where "internal_proces…` | 1.14 / `select * from "notifications" where "notifications"."notifiable_type" = ? and "notifications"."notifiable_id" = ? and "notifications"."notifiable_id" is not null and "org…` |
| sales-executive / reports | 11.85 / `select * from "leads" where "internal_processing_pending" = ? and "delivery_pending" = ? and ("assigned_to" = ? or exists (select * from "followups" where "leads"."id" = …` | 16.78 / `select * from "leads" where "internal_processing_pending" = ? and "delivery_pending" = ? and ("assigned_to" = ? or exists (select * from "followups" where "leads"."id" = …` |

## Index inventory and recommendations

The inventory below is the **actual migrated SQLite test schema**, not a claim about deployed MySQL. Laravel/MySQL creates supporting FK indexes automatically in cases where SQLite does not; missing SQLite FK indexes are not verified missing MySQL indexes. All table/index metadata from the fixture is retained in the JSON.

| Table | Observed indexes (column order) |
|---|---|
| `leads` | `leads_archived_at_index` (archived_at); `leads_archived_internal_idx` (archived_at, internal_processing_pending); `leads_assigned_stage_idx` (assigned_to, current_stage); `leads_booking_status_index` (booking_status); `leads_client_stage_idx` (client_id, current_stage); `leads_created_at_index` (created_at); `leads_current_stage_index` (current_stage); `leads_delivered_at_index` (delivered_at); `leads_delivery_at_index` (delivery_at); `leads_delivery_pending_index` (delivery_pending); `leads_executive_response_due_at_index` (executive_response_due_at); `leads_internal_processing_pending_index` (internal_processing_pending); `leads_lead_code_unique` (lead_code); `leads_mobile_index` (mobile); `leads_replacement_status_index` (replacement_status); `leads_status_index` (status); `primary` (id) |
| `lead_assignments` | `lead_assignments_created_at_index` (created_at); `primary` (id) |
| `followups` | `followups_assigned_status_due_index` (assigned_to, status, due_at); `followups_created_at_index` (created_at); `followups_due_at_index` (due_at); `followups_escalation_due_at_index` (escalation_level, due_at); `followups_lead_status_idx` (lead_id, status); `followups_requested_by_status_index` (requested_by, request_status); `followups_status_due_at_index` (status, due_at); `followups_status_index` (status); `primary` (id) |
| `lead_activities` | Not present in this migrated schema; do not assume this table exists in production. |
| `organizations` | `organizations_created_at_index` (created_at); `organizations_status_index` (status); `primary` (id) |
| `projects` | `primary` (id); `projects_created_at_index` (created_at); `projects_name_index` (name); `projects_status_index` (status) |
| `campaigns` | `campaigns_created_at_index` (created_at); `campaigns_status_index` (status); `primary` (id) |
| `users` | `primary` (id); `users_created_at_index` (created_at); `users_email_unique` (email); `users_name_index` (name); `users_phone_index` (phone); `users_status_index` (status) |
| `client_wallets` | `client_wallets_client_id_unique` (client_id); `client_wallets_created_at_index` (created_at); `primary` (id) |
| `credit_transactions` | `credit_transactions_client_id_created_at_index` (client_id, created_at); `credit_transactions_created_at_index` (created_at); `credit_transactions_delivery_charge_lead_id_unique` (delivery_charge_lead_id); `credit_transactions_transaction_type_index` (transaction_type); `primary` (id) |
| `notifications` | `notifications_created_at_index` (created_at); `notifications_event_key_unique` (event_key); `notifications_inbox_index` (organization_id, notifiable_id, read_at, created_at); `notifications_notifiable_read_idx` (notifiable_type, notifiable_id, read_at); `notifications_notifiable_type_notifiable_id_index` (notifiable_type, notifiable_id); `sqlite_autoindex_notifications_1` (id) |
| `sales_teams` | `primary` (id); `sales_teams_created_at_index` (created_at); `sales_teams_ownable_type_ownable_id_index` (ownable_type, ownable_id) |
| `sales_team_members` | `primary` (id); `sales_team_members_created_at_index` (created_at) |
| `general_settings` | `general_settings_user_key_unique` (user_id, key); `primary` (id) |
| `audit_logs` | `audit_logs_action_index` (action); `audit_logs_created_at_index` (created_at); `audit_logs_org_created_idx` (organization_id, created_at); `audit_logs_organization_id_index` (organization_id); `audit_logs_subject_composite_idx` (subject_type, subject_id, id); `audit_logs_subject_id_index` (subject_id); `audit_logs_subject_type_index` (subject_type); `primary` (id) |
| `executive_response_logs` | Not present in this migrated schema; do not assume this table exists in production. |
| `sla_logs` | Not present in this migrated schema; do not assume this table exists in production. |

- Existing useful composites include leads `(assigned_to,current_stage)`, `(client_id,current_stage)`, `(archived_at,internal_processing_pending)`; followups `(assigned_to,status,due_at)`, `(lead_id,status)`; general_settings unique `(user_id,key)`; credit_transactions `(client_id,created_at)` and unique delivery-charge key; notification morph/read and tenant/inbox indexes; audit subject/id and organization/created indexes.
- Candidate for MySQL EXPLAIN validation: assigned executive + archive/visibility + date/order filters on leads. The existing assigned/stage index may suffice; an additional wider composite has storage/write costs and must match the actual chosen query plan and selectivity.
- Candidate: `lead_assignments(lead_id, unassigned_at)` and/or `(assigned_to, assigned_at)` only if deployed metadata and actual SLA/assignment plans show missing useful coverage. These patterns are not approval to create either index.
- Candidate: notification inbox ordering `(notifiable_type,notifiable_id,read_at,created_at)` versus existing tenant/inbox and morph/read indexes. Validate the actual NotificationDeliveryService predicate, including its organization restrictions and OR clauses, before widening.
- Single-column prefixes may overlap composites (for example audit organization/subject and followups status). They are **not proven redundant**: uniqueness, FK requirements, different leading columns, index width and production usage matter. No index removal is recommended without usage evidence.
- Global dashboard aggregates legitimately examine many matching leads; a full scan or temporary GROUP BY does not alone prove a missing index. The measured N+1/repeated-query reductions were implemented before considering indexes.
- MySQL `EXPLAIN ANALYZE` availability was not verified. Do not execute it on write statements; even SELECT analysis runs the query. The provided command uses non-executing EXPLAIN only.

## Remaining work / priorities

1. Restore access to the intended MySQL/staging environment; run `php artisan performance:database-audit` there. The command opens a separate read-only transaction, prints actual indexes/FKs/version and bounded sample EXPLAIN plans, and performs no DDL/data changes. Sample actor 0 does not model tenant selectivity; capture real scoped page SQL on staging before approving a migration.
2. Measure browser click → first response → usable state for the active role with network/CPU timings, including hover prefetch, font/CDN assets, cache warm/cold states, JS initialization and network duplicate requests. Controller-only time and scanned-row metrics are currently N/A.
3. Admin dashboard still runs per-period, per-agent, per-project and per-organization aggregate loops; group/batch those separately with tests for date bounds, aliases and tenant restrictions. Builder monthly chart loops remain. No speculative index can remove application-level N+1 work.
4. Follow-up personal tabs and Integration catalogs/dropdown option queries remain candidates for lazy/paginated loading. Verify UX counts and private option scopes before changing behavior.
5. Scale staging datasets across organizations, busy Team Leaders, many projects/forms, notification inboxes and credit ledgers. Test inserts/updates/delivery latency before and after any approved index changes.

## Files and migration status

- Modified: app/Livewire/Dashboard/DashboardShell.php; app/Livewire/Leads/LeadKanban.php; app/Livewire/Settings/MetaAdAccounts.php; app/Models/MetaAdAccount.php; resources/views/layouts/app.blade.php; resources/views/components/ui/theme-customizer.blade.php.
- Added: app/Console/Commands/AuditDatabasePerformance.php; tests/Feature/Performance/NavigationAuditTest.php; tests/Feature/Performance/NavigationOptimizationTest.php; this report and before/after JSON evidence.
- **Migration files created: none.** No verified deployed missing index or approved index change was available. No production database mutation, migration, seeding, index creation/drop, or deployment was performed.

## Reproduce safely

Only run the fixture harness with phpunit.xml isolated SQLite configuration, no cached production configuration. In PowerShell:

```powershell
$env:RUN_NAVIGATION_AUDIT='1'
$env:PERFORMANCE_AUDIT_LABEL='verification'
php vendor/phpunit/phpunit/phpunit tests/Feature/Performance/NavigationAuditTest.php
php vendor/phpunit/phpunit/phpunit tests/Feature/Performance/NavigationOptimizationTest.php
```

Keep the before/after labels when reproducing an actual code revision comparison. Re-running a label overwrites only its documentation JSON artifact. The timing harness is skipped in normal test runs; it does not install runtime instrumentation.

## Validation results

- Both opt-in HTTP benchmark runs completed: 31 role/page combinations × 3 requests each, with all measured responses HTTP 200. Each snapshot contains metadata for 74 migrated fixture tables.
- NavigationOptimizationTest: 2 tests / 11 assertions passed, including tenant/pending/archive count restrictions and query-free use of eager Meta account counts.
- ThemeSettingsTest: 5 tests passed. MetaAdsHierarchySyncTest: 8 tests passed.
- Broader regression invocation: 44 tests, 94 assertions; 15 passed, 29 errored during role fixture setup. RoleBasedLeadManagementTest creates `sales-executive` then assigns missing `Sales Executive`; TeamLeadFilterTest likewise expects unseeded canonical roles. These setup errors occur before exercising optimized code. They were recorded, not hidden or addressed by changing application authorization. This is not a full-suite pass.
- PHP syntax and Blade compilation checked separately. No JavaScript/CSS source changes were made.

Actual-table notes: CreditWallet uses `client_wallets`, Client uses `users`, and SLA timing is stored on lead/assignment/follow-up columns rather than an assumed `sla_logs` table. LeadActivity is a model scaffold whose conventional `lead_activities` table is absent in this migrated fixture; no new activity table was invented for the audit.
