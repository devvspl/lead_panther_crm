# Client lead delivery

Super Admin → Clients → Client Details → Leads contains Client Delivery. It extends `LeadDistributionService::releaseDelivery`, retaining wallet reservations, project/campaign activation checks, notifications and organization assignment rules.

## Deployment

Run `php artisan migrate --force`, then `php artisan queue:restart`. Keep the existing minute-based `schedule:run` cron and configured queue worker running. `client-delivery-cycles` starts due plans and recovers unfinished runs; ordinary `release-scheduled-leads` cannot release a lead enrolled in a plan.

### cPanel cron setup

`/artisan-schedule-run` is not an application route. Calling it with curl or wget returns the HTML 404 page and does not run scheduled tasks. Replace that cron entry with the Artisan CLI command; changing the HTTP URL or suppressing its output does not fix scheduling.

In the server terminal, change to the Laravel project root (the folder containing `artisan`), then check PHP and list the scheduled tasks:

```sh
php -v
php artisan schedule:list
```

Generate a cron line using that server's PHP executable and project path:

```sh
printf '* * * * * %s %s/artisan schedule:run >> %s/storage/logs/scheduler-cron.log 2>&1\n' "$(command -v php)" "$PWD" "$PWD"
```

In cPanel set Minute, Hour, Day, Month and Weekday to `*`. Paste the generated command after the five stars into Command. Use the PHP CLI version configured for this application. The paths must be server paths, not local Windows paths or a website URL.

Keep only one cron entry invoking this application's scheduler. Do not remove unrelated application cron entries without checking their purpose. The scheduler already starts a bounded queue worker every minute on shared hosting.

After saving, wait at least one minute and inspect `storage/logs/scheduler-cron.log`, `storage/logs/laravel.log` and the application's scheduled-task run history. For an immediate end-to-end check, `php artisan schedule:run -v` runs due tasks, including real delivery and notification work. A successful scheduler invocation does not guarantee a lead delivery: the plan must be active, due, funded and have eligible queued leads. Keep the scheduler cron at every minute even when a client receives one lead every 15 minutes; the delivery plan controls that interval.

## Operation

Choose an active billing client and its project/campaign, configure delivery settings and save. The existing minute-based scheduler automatically discovers eligible undelivered leads, including records created before the plan. Queue All Eligible is no longer required or displayed. Each lead can have only one transfer record; enrollment does not copy customer records.

The source organization's saved Internal Processing switch controls approval. When enabled, delivery requires a verified internal processing record with verification date, authorized handover request and a currently permitted forwarding status. Older unprocessed intake enters the existing internal queue automatically. Approved handovers enter the saved delivery plan and wait for its batch schedule. When disabled, delivery skips internal approval and does not wait for a legacy internal pending flag or a separate sharing-confirmation timestamp. Existing internal history and explicitly recorded confirmations remain intact; approval/consent records are never fabricated. Manual Push cannot bypass enabled internal processing. The final send check rechecks the saved switch even for a previously selected batch.

The simplified form preselects the current client and single available project/campaign. Push defaults to Immediately, uses the selected lead count and leaves the daily limit optional. Dates, working hours, timezone and the extra cycle cap are under Advanced scheduling. A single Push confirmation records sharing permission; separate confirmation checkboxes are not required.

On the next scheduler tick, active automatic plans enroll pre-existing eligible leads without a manual queue action. Enrollment can happen outside working hours, but sending always respects the saved window, dates, interval, quantity and daily limit. One lead per cycle sends one, with remaining leads carried forward. Ready counts leads passing the current Internal Processing rule, including records awaiting discovery; Pending counts enrolled pending/queued records, including internal approval holds. Delivered and Failed show actual transfer outcomes. Awaiting internal approval is zero when the switch is disabled.

Manual Only disables automatic cycles, but Push explicitly starts one cycle for newly queued leads. Send Cycle Now processes other existing pending leads. Other frequencies enable automation directly. Send All ignores quantity and maximum-per-cycle restrictions; the independently enabled daily limit still applies. Only successful transfers consume daily capacity, counted in the selected timezone. Destination credit and activation checks always apply. Plan-based transfers use plan batch timing/capacity instead of the old campaign per-lead interval and cap.

Configuration changes preserve queued items and their saved destinations. A running cycle retains its selected item count; changed working windows and daily limits are checked before each release. Pause finishes at the next per-lead transaction boundary. Stop returns unprocessed items to pending without deleting them. Resume restores processing. Active automatic plans retry eligible failed transfers in subsequent due cycles, with exponential backoff from one minute up to one hour. Fresh leads take priority over retries. Previous attempts, failure reasons and batch history remain available, and enrollment/retry events are audited. Manual plans retain explicit Retry and Super Admin Send Cycle Now. A client acceptance record requires an explicit Super Admin confirmation; it is not inferred from delivery.

Delivery history filters and Excel export apply only to the source organization's transfer records. History shows the current transfer state; its History action shows every attempted batch and failure. Leads with transfer history cannot be deleted from the Client Leads bulk action.

## Verification

`php vendor/phpunit/phpunit/phpunit tests/Feature/Leads/ClientDeliveryTest.php`

This test suite uses actual test database records, wallet transactions and the existing release service. Production cron/worker configuration and production database concurrency must also be verified in the deployment environment.
