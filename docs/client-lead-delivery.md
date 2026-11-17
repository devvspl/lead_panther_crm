# Client lead delivery

Super Admin → Clients → Client Details → Leads contains Client Delivery. It extends `LeadDistributionService::releaseDelivery`, retaining wallet reservations, project/campaign activation checks, notifications and organization assignment rules.

## Deployment

Run `php artisan migrate --force`, then `php artisan queue:restart`. Keep the existing minute-based `schedule:run` cron and configured queue worker running. `client-delivery-cycles` starts due plans and recovers unfinished runs; ordinary `release-scheduled-leads` cannot release a lead enrolled in a plan.

## Operation

Select one or more source leads and choose Push to Client, or use the Push button on an individual table row. Choose an active billing client and its project/campaign, configure settings and confirm consent. Queue All Eligible enrolls the source organization's eligible, undelivered leads. Internal verification must finish before enrollment. Each lead can have only one transfer record. Enrollment does not copy customer records.

Manual Only waits for Send Cycle Now. Automatic schedules process the enrolled queue at the selected frequency. Send All ignores quantity and maximum-per-cycle restrictions; the independently enabled daily limit still applies. Only successful transfers consume daily capacity, counted in the selected timezone. Destination credit and activation checks always apply. Plan-based transfers use plan batch timing/capacity instead of the old campaign per-lead interval and cap.

Configuration changes preserve queued items and their saved destinations. A running cycle retains its selected item count; changed working windows and daily limits are checked before each release. Pause finishes at the next per-lead transaction boundary. Stop returns unprocessed items to pending without deleting them. Resume restores processing. Failed items wait for Retry, which retains previous attempt records. A client acceptance record requires an explicit Super Admin confirmation; it is not inferred from delivery.

Delivery history filters and Excel export apply only to the source organization's transfer records. History shows the current transfer state; its History action shows every attempted batch and failure. Leads with transfer history cannot be deleted from the Client Leads bulk action.

## Verification

`php vendor/phpunit/phpunit/phpunit tests/Feature/Leads/ClientDeliveryTest.php`

This test suite uses actual test database records, wallet transactions and the existing release service. Production cron/worker configuration and production database concurrency must also be verified in the deployment environment.
