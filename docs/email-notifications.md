# Email notifications and shared designs

Super Admin can open Settings → Client Profile (`/settings/organization`) and use **Notifications & Email Design**. It contains the system-wide event email switch, three previews, one active design, a test-to-own-email action and recent actual email attempts. Save settings before testing. Non-admin users cannot load or mutate this component.

The designs are Classic Card, Executive Brief and Minimal Letter. `components/email/layout.blade.php` provides the common table-based, inline-styled structure. `BrandedNotificationMail` uses it for notification mail. The `notifications::email` view override also applies it to Laravel password reset and verification mail. Account recovery emails remain available when event email is disabled. Notification content is escaped; no arbitrary HTML editor or remote template execution is introduced.

Every newly created notification passing the existing `NotificationDeliveryService::sendWeb` recipient/category rules schedules `SendNotificationEmail` after the business transaction commits. A rolled-back action sends no email. Existing inbox records are not backfilled or bulk emailed. Global disable and current recipient status, organization, preferences and quarantine are rechecked by the worker.

Each mail job has a unique notification reference in `notification_logs`, a recipient-specific cache lock, three queue attempts and backoff. Jobs already recorded as sent do not send again. Existing retry controls recognize these email references and dispatch the correct job. SMTP cannot guarantee exactly-once delivery if a process dies after the provider accepts a message but before the database records success; provider-level idempotency would be needed to eliminate that final acknowledgement window.

Email **sent** means the configured mail transport accepted the message. It does not confirm inbox delivery, read status or bounce status. Failed transport calls are logged and rethrown for queue retry. Log/array mailers are reported as non-delivering, not successful email delivery. Pending or failed attempts do not get an invented successful send time; `sent_at` is nullable. The migration's down method removes its reference/index while retaining this compatible nullable timestamp.

The older email transport placeholder now calls the real Laravel mailer using the same branded mail. The older push placeholder now returns an explicit failure instead of logging a fictional success.

## Deployment

1. Run migrations and deploy the updated views and production assets.
2. Configure a real mailer and sender through the existing protected environment configuration. Do not paste credentials into the UI or commit them.
3. Run the existing database queue worker, for example `php artisan queue:work --tries=3 --timeout=60`, and restart workers after deployment. Ensure the queue retry timeout exceeds the job timeout and SMTP has an appropriate transport timeout.
4. Save the selected design and email switch, then use **Send Test to My Email**. This sends synchronously to exercise the actual mail transport without a queue worker, is rate-limited, and cannot target an arbitrary address. A failed transport produces an error instead of a queued-success message. SMTP timeout defaults to 30 seconds and can be set with MAIL_TIMEOUT.

Only local readiness was inspected: SMTP and credentials were configured, queue was database, broadcasting was log. No real SMTP test email or hosted deployment was performed during implementation. A hosted mailbox test and worker readiness check are still needed there.

In-app notifications already persist to the database and poll for updates; configured broadcasting can supplement polling. Browser push outside the open app is **not configured**: this repository has no Web Push subscription/service worker/provider. The screen reports that accurately and does not request misleading notification permission.

## Verification

The email and web notification suites passed across EmailNotificationTest, EmailQueueDispatchTest and WebNotificationTest. Coverage includes actual commit/rollback queue boundaries, duplicate suppression, active-design persistence/validation, escaped rendering of all three designs, branded password-reset rendering, wrong-organization isolation, admin permissions, disabled email, transport failures and the existing in-app workflows. Mail tests use a fake transport; no external delivery is claimed. Production assets build successfully.

The real-commit test uses an isolated in-memory migration setup because older unrelated project migrations cannot all roll back cleanly on SQLite. The new email migration's own unique-index removal was corrected during testing.
