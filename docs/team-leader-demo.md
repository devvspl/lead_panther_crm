# Team Leader support and Deltek accounts

The current seeder is `DeltekSeeder` and uses `@deltek.leadpanther.test` addresses. See [tracked seeding and revert](seeder-revert.md) for the naming upgrade and rollback workflow. Older names below document the original implementation.

## Architecture and access

The application uses Spatie roles/permissions, organization ownership through users.organization_id, polymorphic sales_teams ownership, and sales_team_members membership. There was no Team Leader navigation or access scope. The old RoleSeeder also truncated roles and reassigned users by name/email; it now adds roles and permissions without deleting existing grants or changing existing users' roles.

The migration `2026_11_15_000002_add_team_leader_support.php` adds nullable sales_teams.leader_id and installs Team Leader permissions. Existing membership tables remain in use. No duplicate team hierarchy was introduced.

Team Leader routes are `/team-leader/dashboard` and `/team-leader/team`. Navigation includes Team Leads, Team Management, Follow-ups, Reports and Notifications. TeamAccess checks active leadership, active membership, organization ownership and granted permissions. Leads must belong to the same organization, have been delivered to the client, and be assigned to an active executive in a team led by that user. Unassigned organization leads and other leaders' teams are excluded. Projects/campaigns and report lookups follow the same visible lead scope.

Team Leaders can create executives in their own team, activate/deactivate their team memberships, inspect their team pipeline, and manually reassign visible leads among their own active executives. They cannot promote users, change team leadership, import leads, operate organization credits/integrations, or open Super Admin modules. Client-side deal stages remain executive operations. Builder/Owner team management supports creating teams, selecting leaders, promoting existing members to Team Leader, and assigning existing executives to teams. Existing Super Admin user create/edit role dropdowns read the installed Team Leader role from the database.

Permissions added: view-team-leads, assign-team-leads, manage-team-members, view-team-reports, view-team-followups, manage-teams and assign-team-leaders. Existing view-leads, assign-leads and view-reports permissions are reused. Audit observers already cover users, roles, teams, memberships and lead assignments.

## Demo accounts

Organization: **Deltek Demo**. Team: **Deltek Sales Team**. Organization owner: **Deltek**. Team leader: **Brijesh**. Ten team executives: **SE1** through **SE10**.

| Name | Email | Role |
| --- | --- | --- |
| Deltek | deltek@demo.leadpanther.test | Builder/Developer |
| Brijesh | brijesh@demo.leadpanther.test | Team Leader |
| SE1 | se1@demo.leadpanther.test | Executive |
| SE2 | se2@demo.leadpanther.test | Executive |
| SE3 | se3@demo.leadpanther.test | Executive |
| SE4 | se4@demo.leadpanther.test | Executive |
| SE5 | se5@demo.leadpanther.test | Executive |
| SE6 | se6@demo.leadpanther.test | Executive |
| SE7 | se7@demo.leadpanther.test | Executive |
| SE8 | se8@demo.leadpanther.test | Executive |
| SE9 | se9@demo.leadpanther.test | Executive |
| SE10 | se10@demo.leadpanther.test | Executive |

Common initial password: `password`, stored using Laravel Hash. Executive is the application's canonical Sales Executive role. Unique international-format demo mobile numbers start at 919000010001; collisions with existing users are skipped. Reserved `.test` emails do not send real email.

Run `php artisan migrate --force`, then `php artisan db:seed --class=DeltekDemoSeeder --force`. The targeted migration and seeder were run successfully in the configured project database during this task. Read-only credential verification returned login_valid=true for all twelve accounts. No Hostinger deployment was performed.

The seeder is opt-in and is not automatically run by production migrations. It creates roles before users, reuses existing demo records and team memberships, and never resets existing passwords or profiles. An email belonging to a different organization/name fails the seeder rather than overwriting that account. No sample leads or credit transactions are inserted.

## Validation and limitations

TeamLeaderTest covers actual login and dashboard rendering for all twelve accounts, idempotent seeding/password preservation, Super Admin user creation, owner leadership assignment, team-only lead/member/report/follow-up visibility, reassignment boundaries, revoked membership, cross-organization lead ownership, and forbidden admin/user-management routes.

Focused regression checks also cover additive role seeding, existing Builder/Executive lead operations, delivery settings, reports and Super Admin notification/audit management. Frontend production build passed. Tests use isolated SQLite data; the configured database seed was separately verified without printing password hashes.

The focused suite passed 67 tests / 502 assertions. Final Team Leader and additive-role checks passed 11 tests / 142 assertions, including the additional project/campaign scope and user-edit checks. The wider legacy suite still has unrelated failures: UserManagerModalTest's eight cases load LeadSeeder fixtures with campaign/lead owners in different organizations; BuilderRoleSeparationTest expects eleven lead table columns whereas the existing verification/handover columns make thirteen; three older RoleScoping cases expect undelivered Builder leads or a Builder bulk-import navigation item. The previously known missing-followup-route navigation regression is excluded from the focused suite. These legacy cases are not claimed as passing.

## Files

New: TeamAccess, TeamLeaderAccess middleware, TeamLeaderRoleSeeder, DeltekDemoSeeder, TeamLeaderTest, the team leadership migration, this document.

Extended: RoleSeeder and its tests; User, Lead, SalesTeam and ClientScope; NavigationConfig; web routes and middleware registration; BuilderTeamManager and its view; the advanced-table action visibility; LeadKanban and its view; DashboardShell and its view; login redirect; FollowupList; ReportsContainer, RevenueAndBookings and SalesExecutivePerformance; UserInvite access protection.
