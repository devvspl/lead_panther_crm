# Lead Panther CRM — Developer Setup & Operations Guide

> **Official Developer Setup, Ingestion Webhook Reference, and Portal Operations Guide**  
> Public Web Version: [`/docs`](http://localhost:8000/docs) (also deployed to `/documentation`)

---

## 📋 Table of Contents
1. [System Overview & Architecture](#1-system-overview--architecture)
2. [Prerequisites & Technology Stack](#2-prerequisites--technology-stack)
3. [Local Development Environment Setup](#3-local-development-environment-setup)
4. [Environment Configuration (.env)](#4-environment-configuration-env)
5. [Inbound Webhooks & API Integration](#5-inbound-webhooks--api-integration)
   - [Meta (Facebook & Instagram) Lead Ads Webhook](#meta-facebook--instagram-lead-ads-webhook)
   - [Google Ads Lead Form Webhook](#google-ads-lead-form-webhook)
   - [Property Portals (99acres / MagicBricks)](#property-portals-99acres--magicbricks)
   - [Owned Portals & Custom Landing Pages](#owned-portals--custom-landing-pages)
   - [Direct REST API Manual Ingestion](#direct-rest-api-manual-ingestion)
6. [Core Business Engines & Logic](#6-core-business-engines--logic)
   - [90-Day Deduplication Rules](#90-day-deduplication-rules)
   - [Atomic Credit Wallet Engine](#atomic-credit-wallet-engine)
   - [Sales Routing & Distribution Engine](#sales-routing--distribution-engine)
   - [15 / 30-Minute SLA Engine](#15--30-minute-sla-engine)
   - [Replacement Guarantee & Refund Claims](#replacement-guarantee--refund-claims)
7. [Portal User Guide by Role](#7-portal-user-guide-by-role)
   - [Super Admin](#super-admin)
   - [Builder Admin](#builder-admin)
   - [Channel Partner](#channel-partner)
   - [Sales Executive](#sales-executive)
   - [Broker / Executive Operations](#broker--executive-operations)
8. [Automated Testing & Quality Assurance](#8-automated-testing--quality-assurance)
9. [Troubleshooting & Operations](#9-troubleshooting--operations)

---

## 1. System Overview & Architecture

Lead Panther CRM is an enterprise Multi-Tenant Lead Management System (LMS) purpose-built for real estate developers, agencies, and channel partners. It unifies lead capture across Meta Ads, Google Ads, Property Portals, and CSV bulk uploads into an automated, credit-gated distribution pipeline.

```
[LEAD SOURCES: Meta, Google, 99acres, Portals, CSV]
                     │
                     ▼
        [API / WEBHOOK GATEWAY] (Auth & Signature Check)
                     │
                     ▼
      [LEAD INGESTION ENGINE] (ProcessInboundLeadJob)
                     │
                     ▼
      [NORMALIZATION & VALIDATION] (Phone, Name, City, UTM)
                     │
                     ▼
        [90-DAY DUPLICATE CHECK]
        ├── Duplicate Found ──► [LOG TO METADATA] (No duplicate lead, ZERO credits deducted)
        └── New Lead
                     │
                     ▼
      [PERMANENT LEAD MASTER ID] (LP-YYYY-XXXXXXXX)
                     │
                     ▼
        [PROJECT / FORM MAPPING]
                     │
                     ▼
           [CREDIT WALLET CHECK] (lockForUpdate)
           ├── Zero Balance ──► [PAUSE CAMPAIGN] (status: pending_credit, Alert Client)
           └── Balance OK   ──► [RESERVE 10 CREDITS] (CreditTransaction logged)
                     │
                     ▼
        [SALES DISTRIBUTION ENGINE] (Round Robin / Capacity)
                     │
                     ▼
     [MULTI-CHANNEL NOTIFICATIONS] (WhatsApp, SMS, CRM In-App)
                     │
                     ▼
        [15/30 MIN SLA CLOCK STARTS]
        ├── Contacted in <= 30 min ──► [SLA MET] (Eligible for Replacement Guarantee)
        └── Late or No Contact     ──► [SLA BREACHED] (Forfeits Replacement Guarantee)
                     │
                     ▼
      [KANBAN SALES JOURNEY: Contacted ──► Meeting ──► Site Visit ──► Proposal ──► Booking ──► CLOSED WON 🏆]
```

---

## 2. Prerequisites & Technology Stack

| Layer | Requirement | Notes |
| :--- | :--- | :--- |
| **PHP** | 8.3+ | Required extensions: `pdo_mysql`, `mbstring`, `openssl`, `zip`, `gd`, `bcmath`, `curl` |
| **Composer** | 2.x | PHP dependency manager |
| **Node.js** | 18+ / NPM 9+ | Vite frontend bundling (TailwindCSS 3 & Alpine.js) |
| **Database** | MySQL 8.0+ / MariaDB 10.5+ | InnoDB engine with pessimistic row-level locking |
| **Queue** | Redis or Database | Background processing of webhook payloads and lead distribution |
| **Framework** | Laravel 12 + Livewire 3 | Server-driven reactive single-page components |

---

## 3. Local Development Environment Setup

Run the following terminal commands to clone and set up the codebase:

```bash
# 1. Clone repository
git clone https://github.com/your-org/lead_panther_crm.git
cd lead_panther_crm

# 2. Install PHP dependencies
composer install

# 3. Create .env file and generate application key
cp .env.example .env
php artisan key:generate

# 4. Run database migrations and seed default test dataset
php artisan migrate --seed

# 5. Install Node dependencies and build frontend assets
npm install
npm run build

# 6. Start the local development server (Terminal 1)
php artisan serve

# 7. Start Queue Worker for background webhook & lead processing (Terminal 2)
php artisan queue:work --tries=3 --timeout=90

# 8. Start Cron / Schedule Worker (Terminal 3, optional)
php artisan schedule:work
```

---

## 4. Environment Configuration (.env)

Ensure the following variables are configured properly in `.env`:

```env
APP_NAME="Lead Panther CRM"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=lead_panther_crm
DB_USERNAME=root
DB_PASSWORD=your_password

QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database

# Optional Gateways
WHATSAPP_GATEWAY=log
SMS_GATEWAY=log
```

---

## 5. Inbound Webhooks & API Integration

### Meta (Facebook & Instagram) Lead Ads Webhook
- **Endpoint**: `ANY /api/webhooks/meta/{portal_account}`
- **Handshake (GET)**: Meta sends `hub.mode=subscribe`, `hub.challenge`, and `hub.verify_token`. The endpoint compares `hub.verify_token` against the stored `verify_token` credential using `hash_equals()`.
- **Inbound Lead (POST)**: Validates HMAC SHA-256 signature against `app_secret` and dispatches `ProcessInboundLeadJob`.

#### Sample Meta Lead Payload
```json
{
  "entry": [
    {
      "id": "1098273645",
      "time": 1724001234,
      "changes": [
        {
          "field": "leadgen",
          "value": {
            "form_id": "49201928374",
            "leadgen_id": "8877665544",
            "created_time": 1724001230,
            "field_data": [
              { "name": "full_name", "values": ["Arjun Verma"] },
              { "name": "phone_number", "values": ["+919876543210"] },
              { "name": "email", "values": ["arjun.verma@example.com"] },
              { "name": "city", "values": ["Mumbai"] },
              { "name": "budget", "values": ["₹1.2 Cr"] },
              { "name": "property_type", "values": ["3 BHK"] }
            ]
          }
        }
      ]
    }
  ]
}
```

### Google Ads Lead Form Webhook
- **Endpoint**: `POST /api/webhooks/google/{portal_account}`
- **Authentication**: Header `X-Google-Key` or payload parameter `google_key`.

#### Sample Google Ads Payload
```json
{
  "lead_id": "goog_987654321",
  "form_id": "goog_form_102",
  "user_column_data": [
    { "column_id": "FULL_NAME", "string_value": "Neha Sharma" },
    { "column_id": "PHONE_NUMBER", "string_value": "9811223344" },
    { "column_id": "EMAIL", "string_value": "neha@example.com" },
    { "column_id": "CITY", "string_value": "Pune" }
  ]
}
```

### Property Portals (99acres / MagicBricks)
- **Endpoint**: `POST /api/webhooks/portal/{portal_account}`
- **Authentication**: API Key in header `X-Portal-API-Key`.

#### Sample Portal Payload
```json
{
  "LeadId": "99AC-449102",
  "Name": "Sunil Patil",
  "Mobile": "9844556677",
  "Email": "sunil.patil@example.com",
  "Project": "Lodha Altamount",
  "City": "Mumbai",
  "Budget": "2.5 Cr",
  "PropertyType": "4 BHK"
}
```

### Direct REST API Manual Ingestion
- **Endpoint**: `POST /api/leads/manual`
- **Method**: `POST`
- **Headers**: `Content-Type: application/json`, `Accept: application/json`

#### cURL Example
```bash
curl -X POST "http://localhost:8000/api/leads/manual" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "Kavita Singh",
    "mobile": "9899887766",
    "email": "kavita.singh@example.com",
    "city": "Bengaluru",
    "budget": "1.0 Cr",
    "property_type": "2 BHK",
    "source": "website_landing_page"
  }'
```

---

## 6. Core Business Engines & Logic

### 90-Day Deduplication Rules
1. Inbound inquiries are checked against existing client leads across a sliding **90-day window** by phone and email.
2. If duplicate:
   - Does **NOT** create a duplicate lead record.
   - Does **NOT** deduct client credits.
   - Appends inquiry timestamp and raw payload into `lead_metadata` (`duplicate_utm_source`, `duplicate_raw_json`).

### Atomic Credit Wallet Engine
1. Uses pessimistic row locking (`CreditWallet::where('client_id', ...)->lockForUpdate()->first()`).
2. Deducts **10 credits** per qualified lead (`CreditTransaction` type `reserve`).
3. If balance <= 0:
   - Sets lead to `pending_credit` hold.
   - Fires `CampaignPaused` event.
   - Sends in-app CRM alert to client to recharge.

### Sales Routing & Distribution Engine
1. Evaluates configured `DistributionRule` for the project:
   - **Round Robin**: Picks sales executive with least recent assignment.
   - **Location**: Maps lead city to designated local specialist.
   - **Priority**: Assigns to highest-priority available user.
   - **Availability**: Assigns only to currently online/active agents.
2. Creates `LeadAssignment` and updates lead stage to `assigned`.
3. Dispatches multi-channel alerts (WhatsApp, SMS, CRM Database Notification).

### 15 / 30-Minute SLA Engine
1. `FirstResponseObserver` detects first outbound contact (`LeadCall`, `LeadMessage`, `LeadCommunication`, or stage advance).
2. Sets `first_response_at`.
3. Response <= 15 min &rarr; Maximum dynamic lead score points.
4. Response <= 30 min &rarr; `SLA Met` (eligible for replacement guarantee).
5. Response > 30 min &rarr; `SLA Breached` (ineligible for replacement guarantee).

### Replacement Guarantee & Refund Claims
1. Agents or clients can submit replacement claims for invalid numbers, wrong contacts, or out-of-budget leads.
2. Requires `sla_met = true`.
3. On approval:
   - Automatically refunds **10 credits** (`CreditTransaction` type `refund`).
   - Marks original lead as `replaced`.
   - Generates free replacement lead (`LP-REP-YYYY-XXXXXXXX`).
   - Writes immutable entry in `audit_logs`.

---

## 7. Portal User Guide by Role

### Super Admin
- **Organizations** (`/admin/organizations`): Manage tenants, assign packages, toggle statuses.
- **Credits & Approval Queue** (`/admin/credits` & `/admin/recharge-requests`): Review client wallet top-up requests.
- **Audit Logs** (`/admin/audit-logs`): Full immutable traceability of mutations, security events, and exports.
- **Git Sync** (`/admin/git-sync`): Production branch deployment and single-click database migration/seeder runner.

### Builder Admin
- **Distribution Rules** (`/projects/{id}/distribution`): Set routing strategy per developer project.
- **Integrations** (`/settings/integrations`): Add Meta App credentials and map Lead Forms to projects.
- **Credit Top-Up** (`/credits`): View wallet transactions and submit recharge claims.
- **Bulk Lead Upload** (`/leads/upload`): Ingest bulk CSV files with error download reports.

### Channel Partner
- **Team Manager** (`/partner/team`): Provision and manage channel partner sales agents.
- **Assigned Leads** (`/leads/kanban`): Track customer progression and sales conversions.

### Sales Executive
- **Daily Routine**:
  1. Receive alert via WhatsApp / SMS / In-App.
  2. Contact lead immediately to beat the 15/30-minute SLA countdown.
  3. Log call or message to record `first_response_at`.
  4. Drag and drop Kanban cards through: *Contacted &rarr; Interested &rarr; Site Visit &rarr; Proposal &rarr; Booking &rarr; Closed Won*.
  5. If number is fake, submit replacement request.

### Broker / Executive Operations
- **Role-Tailored Dashboards**: Direct access to lead queues, call metrics, and performance analytics.
- **Replacement Queue Review** (`/replacements`): Inspect agent SLA compliance badge (`SLA Met` vs `SLA Missed`) before approving or rejecting replacement claims.

---

## 8. Automated Testing & Quality Assurance

Run the comprehensive test suite to verify all unit, feature, and end-to-end integration assertions:

```bash
# Execute entire test suite
php artisan test

# Run end-to-end workflow test specifically
php artisan test --filter=EndToEndLeadWorkflowTest

# Run public documentation route test
php artisan test --filter=PublicDocsTest
```

---

## 9. Troubleshooting & Operations

1. **Inbound Leads Not Showing Up in Kanban?**
   - Check if queue worker is running: `php artisan queue:work`.
   - Check if client wallet has sufficient balance at `/admin/credits`.
   - Inspect raw webhook payloads and error reasons at `/admin/webhook-logs`.
2. **Meta Webhook Handshake Failing?**
   - Verify that your Meta App verify token matches the `verify_token` credential stored for that portal account in CRM.
3. **Queue / Background Task Monitoring**:
   - Inspect failed jobs in `/admin/failed-jobs` with one-click retry capabilities.
