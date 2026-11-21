# User roles, authorization and visibility

## Canonical roles and legacy aliases

`users.organization_id` is tenant membership. `Client extends User` reads `users`; client is also a business identity, not necessarily a distinct current role.

RoleSeeder seeds Super Admin, Builder/Developer, Channel Partner, Broker and Executive. TeamLeaderRoleSeeder adds Team Leader. `User::normalizeRoleName()` maps old role names to canonical roles when available. In particular `client`, old account-manager and sales-executive aliases can normalize to **Executive**. Do not assume “Client/Organization Admin” is a distinct seeded role or that assigning `client` grants organization administration.

`NavigationConfig::getRoleKey()` separately selects super-admin, builder, team-leader, broker, channel-partner, sales-executive, client or guest, in priority order. Menu visibility is not an authorization mechanism. Existing role/permission data may contain additive historical grants; inspect actual records before changing access.

## Access matrix

| Capability | Super Admin | Builder/Developer | Client/organization admin | Team Leader | Sales Executive |
|---|---|---|---|---|---|
| Global organizations/users/integrations/Git/audits | Yes | No | No | No | No |
| Delivered lead visibility | Global plus permitted pending/internal views | Own billing user or same-org owners via panelQuery | Builder behavior if builder role; legacy Client follows older scope | Managed team executives, self, specific unassigned team pool | Assigned-to-self in sales panel |
| Operational lead actions | Yes | Scoped | Actual role/component determines access | Scoped team actions/permissions | Stage-specific actions on own leads |
| Assignment/distribution | Yes | Organization/team scope | Role-dependent | assign-team-leads and managed-team restrictions | Explicitly blocked in lead screens |
| Team management | Admin tools | Organization teams; manage-team permissions | Role-dependent | Managed executive memberships, no role elevation | No |
| Replacement request | Yes | Client workflow | Legacy client workflow where authorized | Requires scoped access | LeadDetail explicitly blocks executive claims |
| Approve/reject replacement | Yes | No | No | No | No |
| Add/deduct/reconcile credits/disputes | Active Super Admin | No privileged mutations | View/request where enabled | No | No |
| Archive/restore/purge/view archives | Active Super Admin | No | No | No | No |
| Lead Journey/source/financial values | Active Super Admin restrictions | Hidden | Hidden | Hidden | Hidden |
| Reports | All | Scoped operations/SLA | Actual role/scope | view-team-reports, managed scope | Own operational scope |
| Notifications | Authorized inbox/admin messages | Authorized org/lead messages | Authorized recipient messages | Authorized team/own messages | Authorized assigned-lead messages |

The matrix states boundaries visible in source, not a completed security certification. Client role ambiguity and old scoping gaps are tracked in KNOWN_ISSUES.

## Enforcement layers

1. LoginForm authenticates only `status=active`; five attempts per email/IP throttle key.
2. EnsureAccountIsActive logs out non-active users on subsequent requests, invalidates sessions and returns JSON 403 or redirects to login. It checks user status, not parent organization status.
3. Route middleware protects Super Admin groups, integrations and role dashboards. Shared lead/report/follow-up routes rely on additional component/service/query checks.
4. TeamLeaderAccess is a route-name allowlist. Livewire update routes remain permitted; each component action must still authorize.
5. TeamAccess::teams requires active user/org, view-team-leads, matching leader and ownership. No leader pivot is tolerated for imported teams, but an explicitly inactive membership denies access.
6. TeamAccess::executives restricts org, executive roles and managed memberships. Existing assignments of inactive executives remain available for leader reassignment under active membership rules.
7. Lead::panelQuery removes ClientScope then applies builder/org-owner, executive-self or leader-team scope and pending/internal hiding. Prefer this for sales panels.
8. ClientScope still exists for several models and has old client-ID versus organization-ID comparisons. It is not interchangeable with panelQuery.
9. ArchiveAwareBuilder filters archived leads/dependents even after withoutGlobalScopes(). Raw DB queries bypass it.
10. LeadSourceVisibility, FinancialVisibility, ClientFacingText, LeadPresenter and exports redact source/monetary information. Hidden buttons alone are insufficient.

## Seeded permissions and practical constraints

RoleSeeder defines view/create/edit/delete/assign/replace leads, manage organization/clients/projects/credits/users, view reports. Builder grants include client/project/credit-management names, but explicit privileged credit actions still demand Super Admin. TeamLeaderRoleSeeder adds team lead/member/report/follow-up permissions; builders receive manage-teams/assign-team-leaders/manage-team-members. Super Admin has Gate::before bypass, which does not bypass explicit service guards.

BuilderTeamManager::managedMember prevents editing protected admins/builders/leaders as ordinary executives. Removing membership is blocked if leads remain assigned; the user account is preserved. Invitation reset links use Laravel password broker.

Financial property/booking/payment values are distinct from lead-credit quantities. Only active Super Admin sees financial fields in guarded views/presenters. Sources and revenue report components reject unauthorized mounting. Lower users see presentation-safe wording rather than Manual/source attribution. Browser push rechecks current recipient/subscription authorization.

## Sources to revisit for an access change

`routes/web.php`, `routes/api.php`, `bootstrap/app.php`, `app/Models/User.php`, `app/Models/Lead.php`, `app/Models/Scopes/ClientScope.php`, `app/Support/NavigationConfig.php`, `TeamAccess.php`, `app/Http/Middleware/TeamLeaderAccess.php`, the target Livewire action and report/export query. Test direct HTTP requests and Livewire actions as well as menu visibility.
