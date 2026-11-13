# Web notifications

## Implementation

The existing Laravel `notifications` table, `User::notifications()` relationship, topbar NotificationBell, and NotificationDeliveryService are reused. Notification rows are real database records. The new migration adds source organization metadata, a unique event key and an inbox index, plus JSON notification preferences on users. Recipient ID remains Laravel's `notifiable_id`; type, title, message, category, related entity, recipient ID and action URL are stored in the existing JSON data payload. `read_at` and timestamps remain standard Laravel columns.

Legacy migration derives organization ownership from the related lead/client rather than trusting the old recipient. Messages with unverifiable ownership are quarantined from organization inboxes. This prevents old arbitrary-user fallback alerts from becoming visible under a wrong organization.

The bell shows six recent items, unread count, individual/read-all actions and the full-history link. `/notifications` paginates 20 items and saves preferences. Every database read/update is scoped to the authenticated recipient and current organization; Super Admins can read only their own notification inbox across source organizations. Read status survives logout. Links use an authenticated resolver which loads the actual related entity and checks current access again. Client-supplied URLs and legacy arbitrary link strings are not followed. Lead links filter by code and open the existing detail drawer; project/campaign links use the existing organization tab or scoped lead filters; summaries open reports.

## Event integrations

- Successful lead delivery: Lead delivered_at transition; organization builders, owner, assigned executive, Super Admins.
- Failed delivery and resumed delivery: actual failure/recovery transitions; responsible organization administrators and Super Admins. Repeated failures are coalesced per lead/day.
- Daily limit reached: real successful-delivery count reaches capacity, or a release is blocked by capacity. Deduplicated per campaign/delivery day.
- Pending delivery slots: real queued lead creation; coalesced per campaign/day.
- Campaign/project configuration, pause and resume: actual changed model fields, not form clicks. Unchanged save/retry emits nothing.
- Assignment/reassignment: actual LeadAssignment creation and direct assignment changes. The service's transient assignment context prevents duplicate alerts from both model observers. Previous assignees receive a removal notice without a link to a lead they no longer control.
- First response: existing FirstResponseRecorded event.
- Meetings/site visits: actual model creation.
- Status/Closed Won/Lost: actual status or stage transitions.
- Replacement submission, return to Super Admin, approval/rejection and replacement generation: actual LeadReplacement changes, inside the existing replacement transaction.
- Follow-up reminders: existing `followups:check-due` command, scoped to the lead's responsible recipients. The arbitrary `User::first()` fallback is removed. Stable due-time keys prevent repeated reminders on scheduler retries; a rescheduled due time can produce a new reminder.
- SLA alerts and daily delivery summaries: `notifications:check-operational`, scheduled every five minutes. SLA starts at recorded delivery time. The organization summary covers the previous local day using its configured delivery timezone and real delivery/queue/failure records.
- Existing CRM template delivery: now writes a real inbox item instead of only recording a simulated CRM delivery log. Retrying that log reuses the same notification key.

Ordinary assignment, follow-up, SLA, replacement, and delivery categories honor user preferences. Critical operational alerts remain enabled for responsible Super Admin/Builder roles; the preferences UI states this explicitly. Existing SMS/WhatsApp assignment and reminder channels are retained and reuse the deduplicated web inbox channel.

## Transactions, privacy and retries

Inbox writes participate in the business transaction. A business rollback removes the alerts too. Retryable business identifiers produce deterministic notification IDs/event keys, with database uniqueness enforcing at-most-once storage. Genuine successive dirty status/configuration transitions have distinct event IDs, including rapid transitions within the same second.

Broadcast invalidations are dispatched after commit, contain no notification text or entity details, and use only `private-App.Models.User.{id}`. The broadcasting authentication endpoint rejects other users' channel subscriptions, including other users in the same organization. The client reloads authoritative scoped database state. Failed broadcasting does not remove persisted notifications; the existing Livewire bell and history poll every 15 seconds even if WebSockets or a queue worker are unavailable.

## Files

- Migration: `database/migrations/2026_11_13_100000_extend_web_notifications.php`.
- Existing service extended: `app/Services/NotificationDeliveryService.php`.
- Event integrations: `app/Observers/WebNotificationObserver.php`, `app/Providers/AppServiceProvider.php`, existing first-response/campaign-pause listeners, `app/Services/LeadDistributionService.php`, and `app/Models/Lead.php` (transient assignment context only).
- Delivery channel/event: `app/Notifications/WebNotification.php`, `app/Notifications/Channels/WebInboxChannel.php`, `app/Events/WebNotificationsChanged.php`, existing assignment/follow-up notification classes.
- Scheduled workflows: existing `CheckDueFollowupsCommand.php`, new `CheckWebNotificationAlerts.php`, and `routes/console.php`.
- UI and access: existing NotificationBell and its view, new NotificationCenter and its view, `resources/views/components/ui/notification-item.blade.php`, `app/Http/Controllers/NotificationController.php`, `routes/web.php`, lead-drawer deep-link handling in LeadKanban and anchors in the existing organization view.
- Preferences cast: `app/Models/User.php`.
- Reverb/Echo: `bootstrap/app.php`, existing `routes/channels.php`, new `config/broadcasting.php`, `config/reverb.php`, `resources/js/bootstrap.js`, `.env.example`, Composer/npm manifests and lockfiles. No separate workspace service is introduced.
- Tests: `tests/Feature/Notifications/WebNotificationTest.php`, updated authorized communication fixtures, and `tests/realtime-notifications.mjs`.

## Verification

Final focused regression run: **67 tests, 352 assertions passed**. Production frontend build and the isolated live Reverb transport test also passed.

Database/Livewire checks cover recipient/organization boundaries, inbox counts, single/read-all persistence, pagination, saved preferences and critical overrides, authoritative record links and revoked access, private channel authentication, transaction rollback, delivery/queue/limit/failure/recovery alerts, assignment and rapid reassignment, status changes, meetings/site visits, first responses, replacement approval/generation, reminder/SLA/summary retries, legacy migration isolation, CRM log retry deduplication, and persistence during a broadcast outage.

`node tests/realtime-notifications.mjs` starts an isolated Reverb process with test-only credentials and no application database, subscribes to an authenticated private channel, rejects an invalid subscription, sends a signed broadcast, verifies live WebSocket delivery, then stops the process. `npm run build` verifies the production Echo client build. Live-host deployment and an end-to-end signed-in browser session were not performed.

The existing role-navigation test `test_missing_followup_route_does_not_break_role_navigation` still fails when it deliberately removes `followups.index`. It was excluded from the focused passing regression run and was not changed by this feature.

## Deployment

1. Install locked dependencies with `composer install --no-dev --optimize-autoloader` and `npm ci`.
2. Run `php artisan migrate --force` and `php artisan optimize:clear`.
3. Set unique `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, the public Reverb host/port/scheme and permitted application origins. Set `BROADCAST_CONNECTION=reverb` and the matching `VITE_REVERB_*` values shown in `.env.example`. Never expose the secret as a Vite variable.
4. Run `npm run build`. Start/supervise `php artisan reverb:start` behind the hosting HTTPS/WebSocket proxy. Keep an existing `php artisan queue:work` worker running for immediate broadcasts and the existing minute scheduler for reminders/operational checks. The application's minute queue-drain fallback also processes broadcast jobs, with additional latency.
5. On hosting that cannot run a persistent WebSocket server, leave broadcasting `null`/`log` and the Vite Reverb key empty. Database notifications still update through 15-second polling.

Reference: [Laravel 12 broadcasting](https://laravel.com/docs/12.x/broadcasting) and [Laravel Reverb](https://laravel.com/docs/12.x/reverb).
