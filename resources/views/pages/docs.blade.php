<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="index, follow">
    <meta name="description"
        content="Lead Panther CRM Developer Setup & Portal User Guide. Comprehensive instructions for environment setup, multi-source webhook integration, credit engine, automated distribution, and role-based portal operations.">
    <link rel="canonical" href="{{ url('/docs') }}">
    <title>Developer Setup & Portal User Guide | Lead Panther CRM</title>
    <x-ui.vite-assets />
</head>

<body class="bg-canvas text-ink font-sans antialiased selection:bg-accent selection:text-white" x-data="{ activeSection: 'overview', copied: null }">

    <!-- Sticky Header -->
    <header class="sticky top-0 z-50 bg-surface/90 backdrop-blur-md border-b border-border transition-all">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <a href="{{ route('landing') }}" class="flex items-center space-x-3">
                    <div class="h-9 w-9 rounded-xl bg-ink text-white flex items-center justify-center font-bold text-lg shadow-sm">
                        LP
                    </div>
                    <span class="text-xl font-bold tracking-tight text-ink">LEAD PANTHER</span>
                </a>
                <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-primary/10 text-primary border border-primary/20">
                    Docs &amp; Portal Guide
                </span>
            </div>

            <nav class="hidden md:flex items-center space-x-6 text-sm font-medium text-muted">
                <a href="#quickstart" class="hover:text-ink transition-colors">Quick Start</a>
                <a href="#workflow" class="hover:text-ink transition-colors">Workflow</a>
                <a href="#webhooks" class="hover:text-ink transition-colors">Webhooks &amp; API</a>
                <a href="#portal-roles" class="hover:text-ink transition-colors">Portal Roles</a>
                <a href="#troubleshooting" class="hover:text-ink transition-colors">Troubleshooting</a>
            </nav>

            <div class="flex items-center space-x-3">
                <a href="{{ route('login') }}" class="text-xs font-semibold text-ink hover:text-muted transition-colors px-3 py-2">
                    Sign In
                </a>
                <a href="{{ route('landing') }}" class="bg-accent hover:bg-black text-white text-xs font-semibold py-2 px-3.5 rounded-lg shadow-xs transition">
                    Back to Home
                </a>
            </div>
        </div>
    </header>

    <!-- Main Container -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

            <!-- Sidebar Navigation (Desktop) -->
            <aside class="hidden lg:block lg:col-span-3 sticky top-24 space-y-6 max-h-[calc(100vh-7rem)] overflow-y-auto pr-3 text-xs">
                <div class="p-4 rounded-xl bg-surface border border-border space-y-4">
                    <div class="font-bold text-xs uppercase tracking-wider text-muted">Documentation Sitemap</div>

                    <div class="space-y-1">
                        <div class="font-semibold text-ink px-2 py-1">1. Getting Started</div>
                        <a href="#overview" class="block px-3 py-1.5 rounded-lg text-muted hover:text-ink hover:bg-canvas transition">System Overview</a>
                        <a href="#prerequisites" class="block px-3 py-1.5 rounded-lg text-muted hover:text-ink hover:bg-canvas transition">Prerequisites &amp; Stack</a>
                        <a href="#quickstart" class="block px-3 py-1.5 rounded-lg text-muted hover:text-ink hover:bg-canvas transition">Local Dev Setup</a>
                        <a href="#env-config" class="block px-3 py-1.5 rounded-lg text-muted hover:text-ink hover:bg-canvas transition">Environment Config (.env)</a>
                    </div>

                    <div class="space-y-1">
                        <div class="font-semibold text-ink px-2 py-1">2. Architecture &amp; Engines</div>
                        <a href="#workflow" class="block px-3 py-1.5 rounded-lg text-muted hover:text-ink hover:bg-canvas transition">Lead Lifecycle Flow</a>
                        <a href="#deduplication" class="block px-3 py-1.5 rounded-lg text-muted hover:text-ink hover:bg-canvas transition">90-Day Deduplication</a>
                        <a href="#credit-engine" class="block px-3 py-1.5 rounded-lg text-muted hover:text-ink hover:bg-canvas transition">Credit Wallet Engine</a>
                        <a href="#distribution" class="block px-3 py-1.5 rounded-lg text-muted hover:text-ink hover:bg-canvas transition">Sales Routing &amp; Rules</a>
                        <a href="#sla-engine" class="block px-3 py-1.5 rounded-lg text-muted hover:text-ink hover:bg-canvas transition">15/30 Min SLA Tracker</a>
                        <a href="#replacements" class="block px-3 py-1.5 rounded-lg text-muted hover:text-ink hover:bg-canvas transition">Replacement Guarantee</a>
                    </div>

                    <div class="space-y-1">
                        <div class="font-semibold text-ink px-2 py-1">3. Ingestion &amp; Webhooks</div>
                        <a href="#webhooks-meta" class="block px-3 py-1.5 rounded-lg text-muted hover:text-ink hover:bg-canvas transition">Meta Lead Ads Webhook</a>
                        <a href="#webhooks-google" class="block px-3 py-1.5 rounded-lg text-muted hover:text-ink hover:bg-canvas transition">Google Ads Webhook</a>
                        <a href="#webhooks-portals" class="block px-3 py-1.5 rounded-lg text-muted hover:text-ink hover:bg-canvas transition">Property Portals (99acres)</a>
                        <a href="#webhooks-owned" class="block px-3 py-1.5 rounded-lg text-muted hover:text-ink hover:bg-canvas transition">Owned Portals &amp; Landers</a>
                        <a href="#api-manual" class="block px-3 py-1.5 rounded-lg text-muted hover:text-ink hover:bg-canvas transition">Manual REST API / CSV</a>
                    </div>

                    <div class="space-y-1">
                        <div class="font-semibold text-ink px-2 py-1">4. Portal User Guide by Role</div>
                        <a href="#role-superadmin" class="block px-3 py-1.5 rounded-lg text-muted hover:text-ink hover:bg-canvas transition">Super Admin Operations</a>
                        <a href="#role-builder" class="block px-3 py-1.5 rounded-lg text-muted hover:text-ink hover:bg-canvas transition">Builder Admin Operations</a>
                        <a href="#role-partner" class="block px-3 py-1.5 rounded-lg text-muted hover:text-ink hover:bg-canvas transition">Channel Partner Operations</a>
                        <a href="#role-sales" class="block px-3 py-1.5 rounded-lg text-muted hover:text-ink hover:bg-canvas transition">Sales Executive Daily Routine</a>
                        <a href="#role-account-manager" class="block px-3 py-1.5 rounded-lg text-muted hover:text-ink hover:bg-canvas transition">Account Manager &amp; Client</a>
                    </div>

                    <div class="space-y-1">
                        <div class="font-semibold text-ink px-2 py-1">5. Operations &amp; Support</div>
                        <a href="#troubleshooting" class="block px-3 py-1.5 rounded-lg text-muted hover:text-ink hover:bg-canvas transition">Troubleshooting &amp; Logs</a>
                        <a href="#testing" class="block px-3 py-1.5 rounded-lg text-muted hover:text-ink hover:bg-canvas transition">Running Automated Tests</a>
                    </div>
                </div>
            </aside>

            <!-- Main Content Area -->
            <main class="lg:col-span-9 space-y-12">

                <!-- Hero Section -->
                <div class="p-6 sm:p-8 rounded-2xl bg-surface border border-border shadow-xs space-y-4">
                    <div class="inline-flex items-center space-x-2 px-3 py-1 bg-canvas border border-border rounded-full text-xs font-semibold text-primary">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span>Official Platform Documentation • v3.0</span>
                    </div>
                    <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-ink">
                        Lead Panther CRM Setup &amp; Operations Manual
                    </h1>
                    <p class="text-sm text-muted leading-relaxed max-w-3xl">
                        This guide provides developers and system administrators with everything required to deploy, configure, ingest multi-channel leads, and operate Lead Panther CRM. It outlines local environment configuration, webhook verification handshakes, atomic credit deduction, 15/30-minute SLA enforcement, and role-based portal workflows.
                    </p>
                </div>

                <!-- 1. System Overview -->
                <section id="overview" class="space-y-4 pt-4 border-t border-border">
                    <div class="flex items-center space-x-2 text-primary font-bold text-xs uppercase tracking-wider">
                        <span>Section 1</span>
                        <span>•</span>
                        <span>Architecture</span>
                    </div>
                    <h2 class="text-2xl font-bold text-ink">System Overview</h2>
                    <p class="text-sm text-muted leading-relaxed">
                        Lead Panther CRM is an enterprise Multi-Tenant Lead Management &amp; Distribution System tailored for real estate developers, agencies, and channel partners. The system is built around six foundational pillars:
                    </p>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2">
                        <div class="p-4 rounded-xl bg-surface border border-border space-y-2">
                            <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-sm">1</div>
                            <h3 class="font-bold text-ink text-sm">Multi-Source Ingestion</h3>
                            <p class="text-xs text-muted">Native webhooks for Meta (FB/IG), Google Ads, Property Portals (99acres/MagicBricks), and REST APIs with HMAC signature verification.</p>
                        </div>

                        <div class="p-4 rounded-xl bg-surface border border-border space-y-2">
                            <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold text-sm">2</div>
                            <h3 class="font-bold text-ink text-sm">90-Day Deduplication</h3>
                            <p class="text-xs text-muted">Prevents duplicate lead records and credit waste by appending duplicate inquiries into marketing metadata without charging credits.</p>
                        </div>

                        <div class="p-4 rounded-xl bg-surface border border-border space-y-2">
                            <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center font-bold text-sm">3</div>
                            <h3 class="font-bold text-ink text-sm">Pessimistic Credit Engine</h3>
                            <p class="text-xs text-muted">Charges 10 credits per valid lead using database row locking (<code class="text-xs bg-canvas px-1 py-0.5 rounded">lockForUpdate</code>) to guarantee zero negative balance race conditions.</p>
                        </div>

                        <div class="p-4 rounded-xl bg-surface border border-border space-y-2">
                            <div class="w-8 h-8 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center font-bold text-sm">4</div>
                            <h3 class="font-bold text-ink text-sm">Automated Sales Routing</h3>
                            <p class="text-xs text-muted">Round-Robin, team capacity limits, and performance weighting route leads instantly with multi-channel alerts (WhatsApp, SMS, CRM).</p>
                        </div>

                        <div class="p-4 rounded-xl bg-surface border border-border space-y-2">
                            <div class="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center font-bold text-sm">5</div>
                            <h3 class="font-bold text-ink text-sm">15 / 30 Min SLA Tracker</h3>
                            <p class="text-xs text-muted">Monitors first contact speed (<code class="text-xs bg-canvas px-1 py-0.5 rounded">first_response_at</code>). Only leads contacted within SLA qualify for replacement refund guarantees.</p>
                        </div>

                        <div class="p-4 rounded-xl bg-surface border border-border space-y-2">
                            <div class="w-8 h-8 rounded-lg bg-cyan-50 text-cyan-600 flex items-center justify-center font-bold text-sm">6</div>
                            <h3 class="font-bold text-ink text-sm">Replacement Guarantee</h3>
                            <p class="text-xs text-muted">Instant credit refund (+10 credits) and automatic free replacement lead (<code class="text-xs bg-canvas px-1 py-0.5 rounded">LP-REP-...</code>) for invalid or fake numbers.</p>
                        </div>
                    </div>
                </section>

                <!-- 2. Prerequisites & Tech Stack -->
                <section id="prerequisites" class="space-y-4 pt-4 border-t border-border">
                    <div class="flex items-center space-x-2 text-primary font-bold text-xs uppercase tracking-wider">
                        <span>Section 2</span>
                        <span>•</span>
                        <span>Environment</span>
                    </div>
                    <h2 class="text-2xl font-bold text-ink">System Prerequisites &amp; Technology Stack</h2>

                    <div class="overflow-x-auto">
                        <table class="w-full text-xs border border-border rounded-xl overflow-hidden bg-surface">
                            <thead class="bg-canvas border-b border-border text-left font-bold text-ink">
                                <tr>
                                    <th class="p-3">Component</th>
                                    <th class="p-3">Required Version</th>
                                    <th class="p-3">Notes &amp; Extensions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border text-muted">
                                <tr>
                                    <td class="p-3 font-semibold text-ink">PHP</td>
                                    <td class="p-3 font-mono">8.3+</td>
                                    <td class="p-3">Extensions: <code class="text-primary">pdo_mysql</code>, <code class="text-primary">mbstring</code>, <code class="text-primary">openssl</code>, <code class="text-primary">zip</code>, <code class="text-primary">gd</code>, <code class="text-primary">curl</code>, <code class="text-primary">bcmath</code></td>
                                </tr>
                                <tr>
                                    <td class="p-3 font-semibold text-ink">Composer</td>
                                    <td class="p-3 font-mono">2.x</td>
                                    <td class="p-3">PHP Dependency Manager</td>
                                </tr>
                                <tr>
                                    <td class="p-3 font-semibold text-ink">Database</td>
                                    <td class="p-3 font-mono">MySQL 8.0+ / MariaDB 10.5+</td>
                                    <td class="p-3">InnoDB engine with transaction row-level locking support</td>
                                </tr>
                                <tr>
                                    <td class="p-3 font-semibold text-ink">Node.js &amp; NPM</td>
                                    <td class="p-3 font-mono">Node 18+ / NPM 9+</td>
                                    <td class="p-3">Vite asset compilation (TailwindCSS 3 &amp; Alpine.js)</td>
                                </tr>
                                <tr>
                                    <td class="p-3 font-semibold text-ink">Framework</td>
                                    <td class="p-3 font-mono">Laravel 12 + Livewire 3</td>
                                    <td class="p-3">Server-driven reactive single-page components without external SPA build overhead</td>
                                </tr>
                                <tr>
                                    <td class="p-3 font-semibold text-ink">Queue Driver</td>
                                    <td class="p-3 font-mono">Redis or Database</td>
                                    <td class="p-3">Processes inbound lead webhooks, SLA notifications, and automated distributions</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- 3. Local Developer Setup -->
                <section id="quickstart" class="space-y-4 pt-4 border-t border-border">
                    <div class="flex items-center space-x-2 text-primary font-bold text-xs uppercase tracking-wider">
                        <span>Section 3</span>
                        <span>•</span>
                        <span>Installation</span>
                    </div>
                    <h2 class="text-2xl font-bold text-ink">Local Developer Setup</h2>
                    <p class="text-sm text-muted leading-relaxed">
                        Follow these exact terminal steps to clone, configure, and boot Lead Panther CRM on your local workstation:
                    </p>

                    <div class="space-y-3">
                        <div class="p-4 rounded-xl bg-surface border border-border space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-xs text-ink">Step 1: Clone Repository &amp; Install Composer Dependencies</span>
                            </div>
                            <pre class="p-3 rounded-lg bg-ink text-gray-100 font-mono text-xs overflow-x-auto"><code>git clone https://github.com/your-org/lead_panther_crm.git
cd lead_panther_crm
composer install</code></pre>
                        </div>

                        <div class="p-4 rounded-xl bg-surface border border-border space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-xs text-ink">Step 2: Environment File &amp; Encryption Key</span>
                            </div>
                            <pre class="p-3 rounded-lg bg-ink text-gray-100 font-mono text-xs overflow-x-auto"><code>cp .env.example .env
php artisan key:generate</code></pre>
                        </div>

                        <div class="p-4 rounded-xl bg-surface border border-border space-y-2" id="env-config">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-xs text-ink">Step 3: Configure Database &amp; Application Keys (.env)</span>
                            </div>
                            <pre class="p-3 rounded-lg bg-ink text-gray-100 font-mono text-xs overflow-x-auto"><code>APP_NAME="Lead Panther CRM"
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
SESSION_DRIVER=database</code></pre>
                        </div>

                        <div class="p-4 rounded-xl bg-surface border border-border space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-xs text-ink">Step 4: Run Migrations &amp; Seed Database</span>
                            </div>
                            <p class="text-xs text-muted">This provisions all tables, roles (<code class="text-xs bg-canvas px-1 rounded">Super Admin</code>, <code class="text-xs bg-canvas px-1 rounded">Builder</code>, <code class="text-xs bg-canvas px-1 rounded">Channel Partner</code>, <code class="text-xs bg-canvas px-1 rounded">Sales Executive</code>), test organizations, demo leads, and default credit packages.</p>
                            <pre class="p-3 rounded-lg bg-ink text-gray-100 font-mono text-xs overflow-x-auto"><code>php artisan migrate --seed</code></pre>
                        </div>

                        <div class="p-4 rounded-xl bg-surface border border-border space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-xs text-ink">Step 5: Install &amp; Build Frontend Assets</span>
                            </div>
                            <pre class="p-3 rounded-lg bg-ink text-gray-100 font-mono text-xs overflow-x-auto"><code>npm install
npm run build
# For hot reload development:
npm run dev</code></pre>
                        </div>

                        <div class="p-4 rounded-xl bg-surface border border-border space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-xs text-ink">Step 6: Start Server &amp; Queue Worker</span>
                            </div>
                            <pre class="p-3 rounded-lg bg-ink text-gray-100 font-mono text-xs overflow-x-auto"><code># Terminal 1: Web server
php artisan serve

# Terminal 2: Background Queue Worker (Mandatory for inbound webhooks &amp; lead jobs)
php artisan queue:work --tries=3 --timeout=90

# Terminal 3 (Optional): Cron / Schedule Worker for SLA monitors
php artisan schedule:work</code></pre>
                        </div>
                    </div>
                </section>

                <!-- 4. End-to-End Workflow Architecture -->
                <section id="workflow" class="space-y-4 pt-4 border-t border-border">
                    <div class="flex items-center space-x-2 text-primary font-bold text-xs uppercase tracking-wider">
                        <span>Section 4</span>
                        <span>•</span>
                        <span>Lead Lifecycle Pipeline</span>
                    </div>
                    <h2 class="text-2xl font-bold text-ink">End-to-End Workflow &amp; Architecture</h2>

                    <div class="p-4 rounded-xl bg-surface border border-border space-y-3">
                        <span class="font-bold text-xs text-ink">Lead Ingestion to Closed Won Flowchart</span>
                        <pre class="p-4 rounded-lg bg-ink text-emerald-400 font-mono text-[11px] leading-tight overflow-x-auto"><code>[LEAD SOURCES: Meta, Google, 99acres, Portals, CSV]
                     │
                     ▼
        [API / WEBHOOK GATEWAY] (Auth &amp; Signature Check)
                     │
                     ▼
      [LEAD INGESTION ENGINE] (ProcessInboundLeadJob)
                     │
                     ▼
      [NORMALIZATION &amp; VALIDATION] (Phone, Name, City, UTM)
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
        ├── Contacted in &lt;= 30 min ──► [SLA MET] (Eligible for Replacement Guarantee)
        └── Late or No Contact     ──► [SLA BREACHED] (Forfeits Replacement Guarantee)
                     │
                     ▼
      [KANBAN SALES JOURNEY: Contacted ──► Meeting ──► Site Visit ──► Proposal ──► Booking ──► CLOSED WON 🏆]</code></pre>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="p-4 rounded-xl bg-surface border border-border space-y-2" id="deduplication">
                            <h3 class="font-bold text-sm text-ink">90-Day Deduplication Rules</h3>
                            <ul class="text-xs text-muted list-disc list-inside space-y-1.5 leading-relaxed">
                                <li>Scans existing leads within a <strong>90-day window</strong> matching mobile or email.</li>
                                <li>Duplicate inquiries append the timestamp and UTM source to <code class="text-primary font-mono">lead_metadata</code>.</li>
                                <li>Guarantees clients are <strong>never charged double credits</strong> for repeat inquiries.</li>
                            </ul>
                        </div>

                        <div class="p-4 rounded-xl bg-surface border border-border space-y-2" id="credit-engine">
                            <h3 class="font-bold text-sm text-ink">Credit Wallet Rules</h3>
                            <ul class="text-xs text-muted list-disc list-inside space-y-1.5 leading-relaxed">
                                <li>Deducts <strong>10 credits</strong> per valid inbound lead (<code class="text-primary font-mono">reserve</code>).</li>
                                <li>Pessimistic locking prevents negative balances under high concurrent traffic.</li>
                                <li>Empty wallets pause incoming campaigns and notify the client user to recharge.</li>
                            </ul>
                        </div>

                        <div class="p-4 rounded-xl bg-surface border border-border space-y-2" id="sla-engine">
                            <h3 class="font-bold text-sm text-ink">15 / 30 Min SLA Tracker</h3>
                            <ul class="text-xs text-muted list-disc list-inside space-y-1.5 leading-relaxed">
                                <li>Captures <code class="text-primary font-mono">first_response_at</code> on outbound calls or messages.</li>
                                <li>Responses under 15 minutes grant maximum lead score points.</li>
                                <li>Responses under 30 minutes fulfill the SLA guarantee condition.</li>
                            </ul>
                        </div>

                        <div class="p-4 rounded-xl bg-surface border border-border space-y-2" id="replacements">
                            <h3 class="font-bold text-sm text-ink">Replacement Guarantee</h3>
                            <ul class="text-xs text-muted list-disc list-inside space-y-1.5 leading-relaxed">
                                <li>Claims filed for invalid or wrong numbers require <code class="text-primary font-mono">sla_met = true</code>.</li>
                                <li>Approved claims refund <strong>+10 credits</strong> back to the client wallet.</li>
                                <li>Generates an automated replacement lead (<code class="text-primary font-mono">LP-REP-...</code>).</li>
                            </ul>
                        </div>
                    </div>
                </section>

                <!-- 5. Webhooks & API Integration -->
                <section id="webhooks" class="space-y-6 pt-4 border-t border-border">
                    <div class="flex items-center space-x-2 text-primary font-bold text-xs uppercase tracking-wider">
                        <span>Section 5</span>
                        <span>•</span>
                        <span>Integration Reference</span>
                    </div>
                    <h2 class="text-2xl font-bold text-ink">Multi-Source Webhook &amp; API Integration</h2>

                    <!-- Meta Webhook -->
                    <div class="p-5 rounded-xl bg-surface border border-border space-y-3" id="webhooks-meta">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <span class="px-2 py-0.5 rounded-md bg-blue-50 text-blue-700 font-bold text-xs border border-blue-200">POST / GET</span>
                                <span class="font-mono text-xs font-bold text-ink">/api/webhooks/meta/{portal_account}</span>
                            </div>
                            <span class="text-xs text-muted">Meta (Facebook &amp; Instagram Lead Ads)</span>
                        </div>
                        <p class="text-xs text-muted leading-relaxed">
                            Handles both Meta's hub challenge verification handshake (<code class="font-mono text-primary">GET</code>) and real-time lead event delivery (<code class="font-mono text-primary">POST</code>).
                        </p>
                        <div class="space-y-1.5">
                            <div class="text-[11px] font-bold text-ink">Sample Inbound Lead Payload:</div>
                            <pre class="p-3 rounded-lg bg-ink text-gray-100 font-mono text-xs overflow-x-auto"><code>{
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
}</code></pre>
                        </div>
                    </div>

                    <!-- Google Webhook -->
                    <div class="p-5 rounded-xl bg-surface border border-border space-y-3" id="webhooks-google">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <span class="px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-700 font-bold text-xs border border-emerald-200">POST</span>
                                <span class="font-mono text-xs font-bold text-ink">/api/webhooks/google/{portal_account}</span>
                            </div>
                            <span class="text-xs text-muted">Google Ads Lead Form Extension</span>
                        </div>
                        <p class="text-xs text-muted">Include the Google Webhook secret in header <code class="font-mono text-primary">X-Google-Key</code> or query param <code class="font-mono text-primary">google_key</code>.</p>
                        <pre class="p-3 rounded-lg bg-ink text-gray-100 font-mono text-xs overflow-x-auto"><code>{
  "lead_id": "goog_987654321",
  "form_id": "goog_form_102",
  "user_column_data": [
    { "column_id": "FULL_NAME", "string_value": "Neha Sharma" },
    { "column_id": "PHONE_NUMBER", "string_value": "9811223344" },
    { "column_id": "EMAIL", "string_value": "neha@example.com" },
    { "column_id": "CITY", "string_value": "Pune" }
  ]
}</code></pre>
                    </div>

                    <!-- Portals (99acres / MagicBricks) -->
                    <div class="p-5 rounded-xl bg-surface border border-border space-y-3" id="webhooks-portals">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <span class="px-2 py-0.5 rounded-md bg-amber-50 text-amber-700 font-bold text-xs border border-amber-200">POST</span>
                                <span class="font-mono text-xs font-bold text-ink">/api/webhooks/portal/{portal_account}</span>
                            </div>
                            <span class="text-xs text-muted">Property Portals (99acres, MagicBricks, Housing)</span>
                        </div>
                        <p class="text-xs text-muted">Supports portal lead delivery formats with project name and requirement details.</p>
                        <pre class="p-3 rounded-lg bg-ink text-gray-100 font-mono text-xs overflow-x-auto"><code>{
  "LeadId": "99AC-449102",
  "Name": "Sunil Patil",
  "Mobile": "9844556677",
  "Email": "sunil.patil@example.com",
  "Project": "Lodha Altamount",
  "City": "Mumbai",
  "Budget": "2.5 Cr",
  "PropertyType": "4 BHK"
}</code></pre>
                    </div>

                    <!-- Manual Lead REST API -->
                    <div class="p-5 rounded-xl bg-surface border border-border space-y-3" id="api-manual">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <span class="px-2 py-0.5 rounded-md bg-purple-50 text-purple-700 font-bold text-xs border border-purple-200">POST</span>
                                <span class="font-mono text-xs font-bold text-ink">/api/leads/manual</span>
                            </div>
                            <span class="text-xs text-muted">Direct Synchronous API / CRM Ingestion</span>
                        </div>
                        <p class="text-xs text-muted">Programmatically submit leads directly into the deduplication, credit check, and distribution pipeline.</p>
                        <pre class="p-3 rounded-lg bg-ink text-gray-100 font-mono text-xs overflow-x-auto"><code>curl -X POST "{{ url('/api/leads/manual') }}" \
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
  }'</code></pre>
                    </div>
                </section>

                <!-- 6. Portal User Guide by Role -->
                <section id="portal-roles" class="space-y-6 pt-4 border-t border-border">
                    <div class="flex items-center space-x-2 text-primary font-bold text-xs uppercase tracking-wider">
                        <span>Section 6</span>
                        <span>•</span>
                        <span>Role Manual</span>
                    </div>
                    <h2 class="text-2xl font-bold text-ink">Portal User Guide: How to Operate by Role</h2>

                    <!-- Super Admin -->
                    <div class="p-5 rounded-xl bg-surface border border-border space-y-3" id="role-superadmin">
                        <div class="flex items-center space-x-2">
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-purple-50 text-purple-700 border border-purple-200">Role</span>
                            <h3 class="text-base font-bold text-ink">Super Admin</h3>
                        </div>
                        <p class="text-xs text-muted leading-relaxed">
                            Super Admins have universal cross-tenant oversight. Key administrative screens and daily responsibilities:
                        </p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs text-muted">
                            <div class="p-3 rounded-lg bg-canvas border border-border space-y-1">
                                <span class="font-bold text-ink block">🏢 Organizations Manager (<code class="text-primary font-mono">/admin/organizations</code>)</span>
                                <span>Create builder and agency tenants, toggle status (active/suspended), assign subscription packages.</span>
                            </div>
                            <div class="p-3 rounded-lg bg-canvas border border-border space-y-1">
                                <span class="font-bold text-ink block">💳 Credits &amp; Wallets (<code class="text-primary font-mono">/admin/credits</code>)</span>
                                <span>Monitor tenant wallet balances, review and approve manual credit recharge requests.</span>
                            </div>
                            <div class="p-3 rounded-lg bg-canvas border border-border space-y-1">
                                <span class="font-bold text-ink block">📜 Audit Logs Browser (<code class="text-primary font-mono">/admin/audit-logs</code>)</span>
                                <span>Inspect immutable audit trail of logins, credit movements, replacement actions, and exports.</span>
                            </div>
                            <div class="p-3 rounded-lg bg-canvas border border-border space-y-1">
                                <span class="font-bold text-ink block">🔄 Git Sync &amp; Deployments (<code class="text-primary font-mono">/admin/git-sync</code>)</span>
                                <span>Perform one-click production git sync, run individual migrations/seeders, view deployment histories.</span>
                            </div>
                        </div>
                    </div>

                    <!-- Builder Admin -->
                    <div class="p-5 rounded-xl bg-surface border border-border space-y-3" id="role-builder">
                        <div class="flex items-center space-x-2">
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200">Role</span>
                            <h3 class="text-base font-bold text-ink">Builder Admin</h3>
                        </div>
                        <p class="text-xs text-muted leading-relaxed">
                            Builder Admins represent real estate developers. Daily management tasks:
                        </p>
                        <ul class="text-xs text-muted list-disc list-inside space-y-1.5">
                            <li><strong>Project Distribution Rules</strong> (<code class="text-primary font-mono">/projects/{id}/distribution</code>): Configure Round-Robin, city-wise routing, and team priority weights.</li>
                            <li><strong>Credit Wallet Management</strong> (<code class="text-primary font-mono">/credits</code>): View credit consumption history and request wallet top-up packages.</li>
                            <li><strong>Integrations &amp; Form Mappings</strong> (<code class="text-primary font-mono">/settings/integrations</code>): Connect Meta Page Tokens, Webhook verification tokens, and map form IDs directly to projects.</li>
                            <li><strong>Bulk Lead Upload</strong> (<code class="text-primary font-mono">/leads/upload</code>): Upload CSV leads with downloadable Excel template and failure error report.</li>
                        </ul>
                    </div>

                    <!-- Sales Executive -->
                    <div class="p-5 rounded-xl bg-surface border border-border space-y-3" id="role-sales">
                        <div class="flex items-center space-x-2">
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Role</span>
                            <h3 class="text-base font-bold text-ink">Sales Executive (Daily Operating Routine)</h3>
                        </div>
                        <p class="text-xs text-muted leading-relaxed">
                            Sales Executives are on the frontline of lead conversion. Their workflow is optimized for speed:
                        </p>
                        <div class="space-y-2 text-xs text-muted">
                            <div class="flex items-start space-x-2">
                                <span class="font-bold text-ink shrink-0">1. Receive Alert:</span>
                                <span>Instant WhatsApp, SMS, and in-app alert when a new lead is assigned.</span>
                            </div>
                            <div class="flex items-start space-x-2">
                                <span class="font-bold text-ink shrink-0">2. Beat the 15/30 Min SLA:</span>
                                <span>Call the customer immediately from the Kanban card (<code class="text-primary font-mono">/leads/kanban</code>). Logging the first call sets <code class="font-mono text-primary">first_response_at</code>.</span>
                            </div>
                            <div class="flex items-start space-x-2">
                                <span class="font-bold text-ink shrink-0">3. Drag &amp; Drop Pipeline:</span>
                                <span>Advance the lead across stages as interactions progress: <em>Contacted &rarr; Interested &rarr; Site Visit &rarr; Negotiation &rarr; Booking &rarr; Closed Won</em>.</span>
                            </div>
                            <div class="flex items-start space-x-2">
                                <span class="font-bold text-ink shrink-0">4. File Replacement Claim:</span>
                                <span>If number is fake or unreachable, click <em>Request Replacement</em> on the lead card. If you contacted within SLA, the claim auto-approves and provides a free replacement lead!</span>
                            </div>
                        </div>
                    </div>

                    <!-- Account Manager / Client -->
                    <div class="p-5 rounded-xl bg-surface border border-border space-y-3" id="role-account-manager">
                        <div class="flex items-center space-x-2">
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">Role</span>
                            <h3 class="text-base font-bold text-ink">Account Manager / Client</h3>
                        </div>
                        <p class="text-xs text-muted leading-relaxed">
                            Account Managers monitor performance and review quality claims while protecting lead privacy:
                        </p>
                        <ul class="text-xs text-muted list-disc list-inside space-y-1.5">
                            <li><strong>PII Masking Protection</strong> (<code class="text-primary font-mono">/account-manager/leads</code>): Phone numbers and emails are automatically masked (<code class="font-mono">98****3210</code>) for privacy compliance.</li>
                            <li><strong>Replacement Approval Queue</strong> (<code class="text-primary font-mono">/replacements</code>): Review pending claims with full visibility into the agent's SLA status (<code class="font-mono text-emerald-600">SLA Met</code> vs <code class="font-mono text-rose-600">SLA Missed</code>) before approving or rejecting with mandatory audit notes.</li>
                        </ul>
                    </div>
                </section>

                <!-- 7. Troubleshooting & Maintenance -->
                <section id="troubleshooting" class="space-y-4 pt-4 border-t border-border">
                    <div class="flex items-center space-x-2 text-primary font-bold text-xs uppercase tracking-wider">
                        <span>Section 7</span>
                        <span>•</span>
                        <span>Operations</span>
                    </div>
                    <h2 class="text-2xl font-bold text-ink">Troubleshooting &amp; Operations</h2>

                    <div class="space-y-3 text-xs">
                        <div class="p-4 rounded-xl bg-surface border border-border space-y-1.5">
                            <span class="font-bold text-ink block">Inbound Webhooks Not Appearing in Kanban?</span>
                            <p class="text-muted">1. Verify queue worker is running: <code class="font-mono bg-canvas px-1 rounded text-primary">php artisan queue:work</code>.<br/>2. Inspect raw payloads and errors at <code class="font-mono bg-canvas px-1 rounded text-primary">/admin/webhook-logs</code>.<br/>3. Check if client credit wallet balance is &gt; 0 at <code class="font-mono bg-canvas px-1 rounded text-primary">/admin/credits</code> (leads with 0 credits wait on <code class="font-mono">pending_credit</code> hold).</p>
                        </div>

                        <div class="p-4 rounded-xl bg-surface border border-border space-y-1.5">
                            <span class="font-bold text-ink block">Meta Webhook Handshake Failing?</span>
                            <p class="text-muted">Ensure your Meta App webhook URL matches <code class="font-mono">https://your-domain.com/api/webhooks/meta/{portal_account_id}</code> and the Verify Token configured in Meta matches the <code class="font-mono">verify_token</code> credential saved for that portal account in CRM.</p>
                        </div>

                        <div class="p-4 rounded-xl bg-surface border border-border space-y-1.5" id="testing">
                            <span class="font-bold text-ink block">Running Automated Test Suite</span>
                            <p class="text-muted">Execute the full end-to-end test suite to verify all 203 automated unit and feature test assertions:</p>
                            <pre class="p-3 rounded-lg bg-ink text-gray-100 font-mono text-xs overflow-x-auto mt-1"><code>php artisan test</code></pre>
                        </div>
                    </div>
                </section>

            </main>
        </div>
    </div>

    <!-- Footer -->
    <footer class="mt-16 border-t border-border bg-surface py-8 text-xs text-muted">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center space-x-2">
                <span class="font-bold text-ink">Lead Panther CRM</span>
                <span>•</span>
                <span>Production v3.0</span>
            </div>
            <div class="flex items-center space-x-6">
                <a href="{{ route('landing') }}" class="hover:text-ink transition">Home</a>
                <a href="{{ route('privacy-policy') }}" class="hover:text-ink transition">Privacy Policy</a>
                <a href="{{ route('docs') }}" class="hover:text-ink transition text-primary font-semibold">Documentation</a>
                <a href="{{ route('login') }}" class="hover:text-ink transition">Sign In</a>
            </div>
        </div>
    </footer>

</body>
</html>
