# Multiple Meta Ad Accounts

The existing `portal_accounts` Meta Page configuration remains the integration root. App ID, App Secret, Page credentials, webhook URLs, and `lead_form_mappings` are retained. The migration does not modify existing forms, mappings, campaigns, or leads.

`meta_ad_accounts` stores independent, encrypted user/system-user tokens beneath that integration. Ad Account IDs are globally unique to avoid duplicate connections. `meta_ad_account_pages` associates those accounts with existing Page integrations; a shared Page is reused rather than copied. `meta_ad_records` keeps remote campaigns, ad sets, ads, and daily campaign insights separate from CRM campaigns, keyed by connection, record type and remote ID. Every row also records the Meta Ad Account ID. Daily insights synchronize the last 30 days; previous imported dates are retained.

## Deployment

After deploying the source and rebuilt assets, run:

```sh
php artisan migrate --force
php artisan optimize:clear
php artisan queue:restart
```

Keep the existing `schedule:run` cron running every minute and the existing queue worker. No additional cPanel cron is required. `meta:sync-ad-accounts` is scheduled every ten minutes through the existing task registry. It queues one job per connection; `meta:sync-leads` continues handling Page forms and lead ingestion. Newly discovered Pages become available in the existing form-routing Page selector and the next Page sync.

Jobs save pagination checkpoints and yield after approximately 25 seconds of work, with a 60-second job timeout (below the existing 90-second queue retry window). Continuation jobs use the same queue. Individual HTTP calls time out after 15 seconds. Account and Page discovery locks prevent overlapping imports. Rate-limit responses set a per-account retry time; transient errors use queue backoff and the scheduler retries due connections. Expired/revoked tokens require reconnecting. Logs contain safe error summaries, never token URLs.

## Administration

In Settings → Integrations → Connected Ad Accounts, use **Add Ad Account** with the numeric Ad Account ID (an `act_` prefix is accepted), display name, and its own token. Portfolio ID and expiry are optional. Saving tests actual access to that Ad Account's campaigns. With App credentials configured, token debugging also validates scopes, token type, and expiry. Different Facebook users and portfolios are supported; no common user is assumed.

The account menu provides Edit/Reconnect, Test Connection, Sync Now, Campaigns & Reporting, and Disconnect. Disconnect clears only that Ad Account token and stops its future jobs; Page connections, mappings, imported records, and audit history remain intact. Stored Ad Account and Page tokens and App Secrets are not prefilled or serialized into the browser on load. Leave secret fields blank to retain saved values.

Remote campaign mappings are scoped to the account. Explicit existing form mappings take precedence. For unmapped forms, a mapped remote campaign can supply the CRM campaign; otherwise a separate CRM campaign is created for that remote identity within the existing project context. Equal names across two accounts do not merge those remote identities. Lead Meta IDs are checked globally against existing metadata before import, including previously archived leads.

Page access is checked separately. The service discovers promoted Pages and only creates a Page integration when Meta returns a Page access token; it never substitutes the ads token for one. Existing Page tokens are preserved. Missing Page permissions are reported independently from successful ads synchronization. Existing Page credentials can still fetch forms even if an Ad Account is disconnected. Newly created Pages inherit the root integration's App credentials and default routing; configure their form routing in the existing Page selector.

Synchronization and connection events appear in Super Admin audit logs and each account's recent history. All new UI actions enforce active Super Admin authorization on every Livewire request; selected account IDs are locked and lookups are scoped to the integration.

The new Marketing API version defaults to `v27.0`, configurable with `META_GRAPH_VERSION`. Existing Page/webhook API versions are unchanged. Meta references: [Ad Account](https://developers.facebook.com/docs/graph-api/reference/ad-account/), [Insights](https://developers.facebook.com/docs/marketing-api/insights/), [Lead Ads](https://developers.facebook.com/docs/marketing-api/guides/lead-ads/).

Automated tests use isolated SQLite databases and mocked Graph responses. Live credentials and production Meta permissions must still be tested after deployment; local tests do not establish live account connectivity.
