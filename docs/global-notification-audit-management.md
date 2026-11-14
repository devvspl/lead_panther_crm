# Global notifications and audit management

## Result

The existing notification center and audit browser are extended, not replaced. Super Admin's Notifications page now shows notifications for all organizations and recipients; the topbar bell remains the administrator's personal inbox. Other roles retain their own organization-scoped personal inbox and cannot expand access by changing filter properties.

The sidebar Notifications link now always targets `/notifications`. A missing named route no longer silently sends the user to Dashboard. The notification endpoint is already registered in `routes/web.php`; deployed route caches must be refreshed and the browser fully reloaded after upload.

## Global notification management

- Filters: source organization, recipient, current recipient role, notification type, read/unread, date bounds and title/message/related lead ID or code search.
- Recipient name/role, organization, type, message, related entity, timestamp and read state come from database records.
- Existing notification resolution checks related-entity access again. Super Admin can open records referenced by other users' notifications; ordinary users cannot.
- The table supports pagination, Inspect details, individual read actions, filtered read-all and filtered Excel export. Read-all explicitly applies to the active filters rather than only the visible page.
- Creation and individual/bulk read actions are audited. Bulk-read audit entries contain the actual affected count and up to 100 notification references, with an explicit truncation flag for larger actions. Exports record their actual row counts too.

## Detailed audit inspection

The original advanced table, search, pagination, quick filters, column preferences and Excel export remain in place. Organization, category and result filters are added.

Inspect now displays Basic Information, Request Details, Change Details and System Details, including actor snapshots, source organization, IP, controller/method, sanitized parameters/payload, user agent, request/session references, related entity IDs and notification references. Before/after JSON and a field-by-field comparison are shown. Copy JSON copies sanitized data; Copy Audit ID and previous/next matching-record navigation are included.

Unavailable historical fields remain unavailable. Older request details already present in from/to payloads are displayed. The migration backfills organization metadata only from explicit recorded organization IDs or an Organization subject ID; it does not guess historical organization ownership or rewrite old payloads.

## Audit capture and integrity

- Existing model observers continue to capture lead creation, assignments, stage/status changes, replacements, projects/campaigns, delivery limits/intervals, users, wallet transactions and system settings.
- Existing authentication subscriber continues to record login/logout/security events.
- Existing request middleware captures controller/Livewire/API/webhook activity and failure responses. Poll refresh noise is skipped. Positional action arguments without reliable field names are masked rather than risking exposure of OTP/password values.
- Notification creation/read model events, filtered read-all, exports, role attachments/removals and permission changes are integrated into the same audit store. Permission synchronization records actual before/after IDs; transient access snapshots avoid inventing previous role state.
- Actor roles/organization are captured at request entry so changing the actor's own privileges does not alter the event's actor snapshot.
- Shared redaction applies recursively to structured and encoded JSON, secret setting values, credential fields, authorization strings, URL credentials and common token-bearing paths. Raw server/database exception text is withheld in favor of safe error/reference information. Raw sessions, cookies and authorization headers are not copied into inspection data.
- Redaction also applies when reading older payloads and exporting/copying them.
- Audit model instance updates/deletes and ordinary Eloquent bulk update/delete/upsert/increment/truncate operations are rejected. This is application-level append-only protection. Privileged raw SQL/migration access remains an administrative capability; restrict production database credentials accordingly.

## Modified files

Database/models:
- `database/migrations/2026_11_14_000001_enrich_audit_log_context.php`
- `app/Models/AuditLog.php`
- `app/Models/Builders/AppendOnlyAuditBuilder.php`
- `app/Models/SystemNotification.php` (view of the existing notification table)
- `app/Models/User.php` (access-change snapshots)

Capture, security and queries:
- `app/Support/AuditContext.php`
- `app/Support/AuditRedactor.php`
- `app/Observers/AuditLogObserver.php`
- `app/Providers/AppServiceProvider.php`
- `app/Http/Middleware/AuditActivityMiddleware.php`
- `app/Services/NotificationDeliveryService.php`
- `app/Http/Controllers/NotificationController.php`
- `app/Support/NavigationConfig.php`

Existing screens/components:
- `app/Livewire/Admin/AuditLogBrowser.php`
- `app/Livewire/Shared/NotificationCenter.php`
- `app/Livewire/Shared/NotificationBell.php`
- `resources/views/livewire/admin/audit-log-browser.blade.php`
- `resources/views/livewire/shared/notification-center.blade.php`
- `resources/views/components/ui/audit-json.blade.php`

Verification:
- `tests/Feature/Admin/GlobalNotificationAuditManagementTest.php`

## Verification

84 tests / 483 assertions passed across the global notification/audit tests, existing audit capture, notifications, communication, delivery, role-based lead management, replacements and first-response SLA workflows. The production frontend build passed.

Verified global access and personal-bell separation, ordinary-user isolation, organization/user/role/type/read/date/search filters, pagination, filtered read-all, actual before/after configuration values, role/permission changes, append-only guards, nested secret masking in storage/inspection/copy/export, API/webhook/failure capture, filtered exports and audit navigation. Migration down/up and legacy payload preservation were also tested. The sidebar regression verifies the actual page and URL targeting with the notification route name removed.

The pre-existing `test_missing_followup_route_does_not_break_role_navigation` remains excluded: it removes `followups.index`, which existing navigation code still requires. No live-host deployment or production database changes were performed. Tests use the application's isolated SQLite database.

## Deployment

Upload the complete updated revision, including `routes/web.php`, the updated NavigationConfig and the new migration. Run:

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan route:cache
```

Deploy the generated frontend build (or run `npm ci` and `npm run build` on the build host). Fully refresh the browser with Ctrl+F5 to replace any persisted sidebar containing the old Dashboard URL. Verify that `php artisan route:list --path=notifications` lists both notification endpoints. Existing queue, scheduler and optional Reverb settings continue to apply.
