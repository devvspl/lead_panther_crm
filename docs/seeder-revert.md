# Tracked seeding and database revert

Git Sync → Seeders now executes seeders through SeederRunService rather than an untracked shell command. Each completed run has a run ID, administrator, timestamp, status and encrypted per-record change manifest. Nested Seeder::call operations belong to the same run.

The seeder row provides **Revert Data**, **Tracked run history**, and a preview listing inserted, updated and deleted row counts by table. Confirmation revalidates the database before changing anything. Inserted rows are deleted, updated rows are restored to their previous values, and deleted rows are restored. Foreign key checks remain enabled. Audit logs remain available after reverting.

Revert refuses changed records, additional foreign-key or polymorphic dependencies, already reverted runs and runs without a manifest. This prevents deletion of real leads, teams or notifications added after seeding. A no-op rerun does not claim ownership of existing data. Revert is transaction-safe and restricted to active Super Admins in the web UI. Encrypted snapshots are never sent to the browser or included in audit payloads.

## Existing untracked runs

Historical audit entries contain a class name and outcome, not row ownership or previous values. Those entries are labelled **Previous run has no rollback manifest**. They cannot be converted into a deletion list by guessing names or email addresses. New runs are tracked; re-running an idempotent seeder does not retroactively claim older records. In particular, an older Deltek installation may produce only naming updates on its next tracked run; reverting that run restores those names, rather than deleting pre-existing accounts.

## Deltek naming

The visible class is **DeltekSeeder**, category **Client Accounts**. Fresh accounts use organization **Deltek** and `deltek@deltek.leadpanther.test`, `brijesh@deltek.leadpanther.test`, and `se1@deltek.leadpanther.test` through `se10@deltek.leadpanther.test`.

On a subsequent DeltekSeeder run, a recognized legacy installation is renamed in place. IDs, passwords, roles, leads and team relationships are preserved; conflicting organization/email names stop the run. The old class remains a hidden compatibility alias for previous CLI commands. Historical audit records are not rewritten.

## Commands and deployment

Apply `2026_11_16_000001_create_seeder_run_tracking.php` using the normal deployment migration process before using tracked seeders.

```sh
php artisan db:seed-tracked --class=DeltekSeeder
php artisan db:seed-tracked --class=CreditPackageSeeder
php artisan db:seed-tracked --preview=RUN_ID
php artisan db:seed-tracked --revert=RUN_ID
```

Standard `db:seed --class=DeltekSeeder` also enters the tracked runner automatically. For other seeders, use the UI or `db:seed-tracked` to obtain a rollback manifest. Direct untracked `db:seed` runs for other classes cannot be reversed through this feature.

Tracking supports the default database connection, primary/non-null unique keys, and transactional DML. DDL, truncate, connection-setting changes, cross-connection writes and unsupported multi-statement SQL are rejected before execution. Touched tables are locked and limited to 50,000 rows per table for an in-process snapshot. MySQL starts a SERIALIZABLE transaction when there is no surrounding transaction. These checks intentionally fail closed rather than execute an irreversible seeder.

This is a database revert, not a rollback of external email, files or API actions. Audit records, the seeder ledger, migration records, sessions, cache and queue infrastructure are retained. No live account deletion or hosted rollback was executed while implementing this feature.

## Changed files

New: SeederRunService; SeederRun and SeederRunChange; TrackedSeeder command; tracking migration; DeltekSeeder; SeederRollbackTest; this document.

Extended: GitSync component and view; GitSyncService; AppServiceProvider singleton registration; DeltekDemoSeeder compatibility alias; TeamLeaderTest account references.

The existing deletion of GitSyncTest.php was preserved. New tests independently verify the seeder UI preview and confirmation.

## Verification

SeederRollbackTest, TeamLeaderTest and RoleSeederTest passed together: 23 tests, 199 assertions. These cover tracked creation, restoration of updates/deletions, failed-run atomicity, dependency protection, encrypted snapshots, Deltek naming migration, idempotence, authorization, and the preview/confirmation UI. The production asset build also passed.
