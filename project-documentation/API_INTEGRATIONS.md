# APIs and integrations

## HTTP endpoints

/api prefix comes from bootstrap/app.php.

| Endpoint | Handler | Current protection |
|---|---|---|
| GET/POST /api/webhooks/meta/{portal_account} | MetaWebhookController | GET configured verify-token challenge; POST authentication incomplete; webhook group throttle 60/min |
| POST /api/webhooks/google/{portal_account} | GoogleWebhookController | Queues logged payload; credential verification incomplete |
| POST /api/webhooks/portal/{portal_account} | PortalWebhookController | Queues logged payload; credential verification incomplete |
| POST /api/webhooks/owned/{portal_account} | OwnedPortalWebhookController | Queues logged payload; credential verification incomplete |
| POST /api/leads/manual | ManualLeadController | No route auth/throttle declared; creates/fetches owned account, dispatchSync input |
| GET /api/leads/{lead} | LeadApiController → LeadResource → LeadPresenter | auth; sales panel additionally checked by panelQuery; older roles use model scope |

No Sanctum dependency or OAuth/API-key auth layer was found in manifest/routes. Do not interpret plain auth as an external token API. Session availability in API middleware requires runtime verification.

Webhook POST handlers currently reject credentials containing a particular invalid marker but do not verify actual HMAC/shared keys. Meta reads a secret but does not calculate/compare body HMAC. This is a confirmed security gap; do not expose these as authenticated integrations until fixed. LogsWebhookPayload redacts only a small top-level credential-key list; nested keys/alternate credential names need review.

## Intake/routing

ProcessInboundLeadJob normalizes through source mapper, fetches Meta lead fields, resolves Page/form mapping, checks Meta ID and 90-day client phone/email duplicates, saves lead/metadata and emits LeadCreated. Duplicate records attach attribution metadata rather than charge/create another lead. Intake fallback can use first Client/User/project and name-only campaign matching; explicit tenant mappings are safer. This is not a proven exhaustive database uniqueness guarantee.

## Meta Page/forms

PortalAccount stores connection name/type/status; IntegrationCredential encrypts values via Laravel encrypted cast and hides encrypted_value. Preserve APP_KEY/previous keys or existing credentials become unreadable. Meta App ID/secret, Page ID/token, verify token and default mappings are managed in IntegrationsManager. Values must not be included in logs/docs/frontend responses.

LeadFormMapping identity is portal_account_id + form_id, with Page/form name/status/creation time and CRM campaign/project/client. MetaLeadSyncService::sync discovers forms with pagination, preserves mappings and polls leads. SyncMetaLeadsCommand loops active Meta portal accounts; limit defaults100 per form and does not promise full historical backfill. Several Page/lead paths hardcode Graph v19.0; source review does not establish upstream API availability.

## Multiple Ad Accounts

Settings/MetaAdAccounts and MetaAdAccountService manage independent account test/sync. MetaAdAccount stores encrypted hidden token, name, unique external ad_account_id, optional portfolio/Facebook connection metadata, expiry/status/retry_after/last sync and hidden resume state.

MetaAdRecord stores kind campaign/adset/ad/insight, remote IDs, source account identity, JSON data, report date and optional CRM campaign mapping. Unique(meta_ad_account_id,kind,remote_id). Insights use campaign/date identity and last 30d daily campaign data.

Service reads only Graph host paths with bearer token, replays cursors instead of API-supplied URLs, checks returned account identity, persists progress in approximately 25s slices, and uses cache/job overlap locks. Transient/rate-limit/expired conditions update status/retry state; thrown job errors are sanitized. Default Graph version is v27.0 through config('services.meta.graph_version',...). Actual environment-to-config wiring must be checked; defaults differ from older Page code.

Page association uses promote_pages, reuses known Page connections, obtains genuine Page tokens for new connections and preserves existing mappings on partial access errors. Ads token is not substituted for Page lead access. account-specific campaignForLead uses remote record identity, avoiding same-name mixing in that branch; older fallback matching remains.

Connecting an account does not guarantee every lead is retrievable: ads permissions, Page access, lead access, valid tokens, form mapping and functioning workers are separate dependencies.

## Notifications and external transport

NotificationDeliveryService::sendWeb authorizes user/org/lead visibility/preferences, writes deterministic notification/event identity in business transaction and dispatches push/email/broadcast after commit. SystemNotification aliases notifications. Use service inbox query, which accommodates compatibility identities; default unreadNotifications alone may miss records.

BrowserPushService uses Minishlink Web Push, VAPID env keys or encrypted local private key file, allowlisted HTTPS endpoints, per-user/org subscriptions and delivery history. Browser permission/subscription required. Reverb/Echo provide realtime when configured, with polling fallback.

EmailDesignService and queued branded mail require real Laravel mail transport; log/array is not external delivery. WhatsAppGateway/SmsGateway bind to LogWhatsAppGateway/LogSmsGateway; NotificationDeliveryService also logs simulated dispatch. A successful log status here is not provider delivery. No provider receipt callback was identified.

No Gemini client/SDK/endpoint/credential config was found in app or config. Treat as unimplemented in this checkout; separately hosted integration remains unverified.

## Git/files/backups

GitSyncService reads encrypted Git portal credentials, runs remote test/fetch/pull/push, scans diff for secrets, creates safety branches and supports revert/reset/restore plus administrative follow-up commands. Symfony Process uses argument arrays. HTTP management is Super Admin; host process/filesystem permission still matters. Authenticated URLs include credentials in process arguments; verify host process visibility and output redaction.

Private documents use documents disk and signed authenticated download; proposals use signed public viewer. Older document tenant checks need ID-domain review. Archive-aware lead check blocks archived document links. Backup package handles configured database/documents disks; no production backup was verified.

## Environment/configuration inventory (names only)

| Area | Relevant names |
|---|---|
| App/auth | APP_ENV, APP_DEBUG, APP_KEY, APP_PREVIOUS_KEYS, APP_URL, APP_TIMEZONE, AUTH_GUARD, AUTH_MODEL, AUTH_PASSWORD_BROKER |
| Database | DB_CONNECTION, DB_URL, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD, DB_FOREIGN_KEYS, relevant SSL options |
| Queue/cache | QUEUE_CONNECTION, DB_QUEUE_CONNECTION, DB_QUEUE_TABLE, DB_QUEUE, DB_QUEUE_RETRY_AFTER, CACHE_STORE, REDIS_* |
| Session | SESSION_DRIVER, SESSION_DOMAIN, SESSION_SECURE_COOKIE, SESSION_SAME_SITE, SESSION_ENCRYPT |
| Mail/providers | MAIL_MAILER, MAIL_HOST, MAIL_PORT, MAIL_SCHEME, MAIL_USERNAME, MAIL_PASSWORD, MAIL_FROM_ADDRESS/NAME, POSTMARK_API_KEY, RESEND_API_KEY, AWS_* |
| Push | WEB_PUSH_ENABLED, WEB_PUSH_PUBLIC_KEY, WEB_PUSH_PRIVATE_KEY, WEB_PUSH_SUBJECT, WEB_PUSH_OPENSSL_CONF |
| Realtime | BROADCAST_CONNECTION, REVERB_APP_ID/KEY/SECRET, REVERB_HOST/PORT/SCHEME, server/origin options; VITE_REVERB_APP_KEY/HOST/PORT/SCHEME |
| Files/backups | FILESYSTEM_DISK, DOCUMENTS_DISK_DRIVER, BACKUP_DISK_DRIVER, BACKUP_NOTIFICATION_EMAIL, MYSQL_DUMP_PATH, optional S3/AWS keys |
| Upload | UPLOAD_MAX_FILE_SIZE_KB; individual components also hardcode limits |

Actual .env values were not read. Meta and Git credentials are primarily database-driven. An env name appearing in source does not prove every relevant code path consumes it.

## Form discovery diagnostics update (21 November 2026)

MetaFormSyncService now owns settings/scheduled Page catalogue discovery. It uses the shared configured Graph client and safe cursor extraction; does not stop on fewer than100 records; persists remote metadata without replacing routing; retains saved mappings after API failures. Settings show fresh/new/updated/saved counts, selected Page, API page count and safe permission/expiry/rate-limit codes. AuditLog stores meta.forms.completed/partial/failed; Laravel logs include correlation run IDs and counts without tokens/raw provider messages. Connected Ad Account history records sync_requested/started, per-kind fetched/new/updated counts and completion totals. Form fetch does not fetch campaigns/ad sets; use Sync Campaigns & Ad Sets for linked tested Ad Accounts. MetaLeadSyncService Page/lead polling now reads services.meta.graph_version; other old connection/webhook calls may still use v19.0.
