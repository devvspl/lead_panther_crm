<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description"
        content="Lead Panther CRM - Automated multi-source lead capture, credit-based distribution, and real-time SLA tracking for real estate builders, channel partners, and sales teams.">
    <title>Lead Panther CRM - Turn Every Lead Into a Closed Deal</title>
    <x-ui.vite-assets />
</head>

<body class="bg-canvas text-ink font-sans antialiased selection:bg-accent selection:text-white">

    <!-- 1. Sticky Top Navigation -->
    <header class="sticky top-0 z-50 bg-surface/90 backdrop-blur-md border-b border-border transition-all">
        <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
            <!-- Logo Left -->
            <a href="{{ route('landing') }}" class="flex items-center space-x-3">
                <div
                    class="h-9 w-9 rounded-xl bg-ink text-white flex items-center justify-center font-bold text-lg shadow-sm">
                    LP
                </div>
                <span class="text-xl font-bold tracking-tight text-ink">LEAD PANTHER</span>
            </a>

            <!-- Nav Links Center -->
            <nav class="hidden md:flex items-center space-x-8 text-sm font-medium text-muted">
                <a href="#features" class="hover:text-ink transition-colors">Features</a>
                <a href="#how-it-works" class="hover:text-ink transition-colors">How it Works</a>
                <a href="#showcase" class="hover:text-ink transition-colors">Showcase</a>
                <a href="#pricing" class="hover:text-ink transition-colors">Pricing</a>
                <a href="#contact" class="hover:text-ink transition-colors">Contact</a>
            </nav>

            <!-- Buttons Right -->
            <div class="flex items-center space-x-4">
                <a href="{{ route('login') }}"
                    class="text-sm font-medium text-ink hover:text-muted transition-colors px-3 py-2">
                    Log in
                </a>
                <a href="{{ route('register') }}"
                    class="bg-accent hover:bg-black text-white text-sm font-medium py-2 px-4 rounded-lg shadow-sm transition ease-in-out duration-150">
                    Get Started
                </a>
            </div>
        </div>
    </header>

    <!-- 2. Hero Section (Two-Column) -->
    <section class="py-20 md:py-28 overflow-hidden relative">
        <div class="max-w-7xl mx-auto px-6 grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-center">
            <!-- Left Column: Copy & Actions -->
            <div class="space-y-8 text-left">
                <div
                    class="inline-flex items-center space-x-2 px-3 py-1 bg-surface border border-border rounded-pill text-xs font-semibold text-accent shadow-xs">
                    <span class="w-2 h-2 rounded-full bg-success animate-pulse"></span>
                    <span>Multi-Source Lead Distribution v3.0</span>
                </div>

                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold text-ink tracking-tight leading-[1.1]">
                    Turn Every Lead Into a Closed Deal
                </h1>

                <p class="text-lg text-muted max-w-xl leading-relaxed">
                    Automated lead capture from Meta Ads, Google PPC, and property portals. Intelligently route leads to
                    sales teams via credit-based wallets, strict SLAs, and transparent replacement workflows.
                </p>

                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-4 pt-2">
                    <a href="{{ route('register') }}"
                        class="bg-accent hover:bg-black text-white font-medium py-3.5 px-6 rounded-lg shadow-sm text-center transition ease-in-out duration-150 text-base">
                        Get Started Free &rarr;
                    </a>
                    <a href="#showcase"
                        class="border border-border bg-surface hover:bg-canvas text-ink font-medium py-3.5 px-6 rounded-lg shadow-sm text-center transition ease-in-out duration-150 text-base">
                        Watch Interactive Demo
                    </a>
                </div>

                <!-- Key Metrics Snippet -->
                <div class="pt-6 border-t border-border grid grid-cols-3 gap-6 text-left">
                    <div>
                        <div class="text-2xl font-bold text-ink">99.8%</div>
                        <div class="text-xs text-muted">SLA Compliance</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-ink">&lt; 3 mins</div>
                        <div class="text-xs text-muted">First Response</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-ink">100%</div>
                        <div class="text-xs text-muted">Tenant Isolation</div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Mocked Dashboard Screenshot with Dotted Background Pattern -->
            <div class="relative lg:pl-4">
                <!-- Dotted Hexagon/Grid Pattern Background -->
                <div class="absolute -inset-4 bg-radial from-slate-200 to-transparent opacity-60 rounded-3xl pointer-events-none -z-10"
                    style="background-image: radial-gradient(#cbd5e1 1.5px, transparent 1.5px); background-size: 16px 16px;">
                </div>

                <!-- Mockup Container -->
                <div
                    class="bg-surface rounded-card border border-border p-2.5 shadow-xl relative transition-transform duration-300 hover:scale-[1.01]">
                    <div class="bg-canvas rounded-xl overflow-hidden border border-border">
                        <!-- Top Window Bar -->
                        <div class="h-9 bg-surface border-b border-border px-4 flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <div class="w-3 h-3 rounded-full bg-rose-400"></div>
                                <div class="w-3 h-3 rounded-full bg-amber-400"></div>
                                <div class="w-3 h-3 rounded-full bg-emerald-400"></div>
                            </div>
                            <div class="text-[11px] font-semibold text-muted tracking-wide uppercase">Lead Panther CRM
                                &bull; Live Pipeline</div>
                            <div class="text-[10px] bg-canvas text-muted px-2 py-0.5 rounded border border-border">⌘F
                                Search</div>
                        </div>

                        <!-- Dashboard Mock Image / Mockup Render -->
                        <div class="relative aspect-[16/10] overflow-hidden bg-slate-900">
                            <img src="{{ asset('images/screenshots/dashboard.png') }}"
                                alt="Lead Panther CRM Dashboard Screenshot"
                                class="w-full h-full object-cover object-top"
                                onerror="this.onerror=null; this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'800\' height=\'500\' viewBox=\'0 0 800 500\'><rect width=\'800\' height=\'500\' fill=\'%230a0a0a\'/><text x=\'50%\' y=\'50%\' fill=\'%23ffffff\' font-family=\'sans-serif\' font-size=\'24\' text-anchor=\'middle\'>Lead Panther Live Dashboard</text></svg>';">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 3. "Trusted By" Logo Strip -->
    <section class="py-12 bg-surface border-y border-border">
        <div class="max-w-7xl mx-auto px-6 text-center">
            <p class="text-xs font-bold uppercase tracking-wider text-muted mb-8">
                Trusted by Top Real Estate Developers &amp; Channel Partners Across the Region
            </p>

            <div
                class="grid grid-cols-2 md:grid-cols-5 gap-8 items-center opacity-70 grayscale hover:grayscale-0 transition-all duration-300">
                <div class="text-lg font-extrabold tracking-widest text-ink">APEX REALTY</div>
                <div class="text-lg font-extrabold tracking-widest text-ink">SKYLINE DEVS</div>
                <div class="text-lg font-extrabold tracking-widest text-ink">VENTURE BUILD</div>
                <div class="text-lg font-extrabold tracking-widest text-ink">URBAN INFRA</div>
                <div class="text-lg font-extrabold tracking-widest text-ink">HORIZON ESTATES</div>
            </div>
        </div>
    </section>

    <!-- 4. Features Grid (6 Cards) -->
    <section id="features" class="py-24 bg-canvas">
        <div class="max-w-7xl mx-auto px-6 space-y-16">
            <div class="text-center max-w-3xl mx-auto space-y-4">
                <h2 class="text-3xl sm:text-4xl font-bold text-ink tracking-tight">
                    Engineered for High-Velocity Lead Conversion
                </h2>
                <p class="text-base text-muted">
                    Built exclusively for real estate ecosystems where response time, lead quality, and team
                    accountability determine revenue success.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Feature 1 -->
                <div
                    class="bg-surface rounded-card border border-border p-6 shadow-sm hover:shadow-md transition-all space-y-4">
                    <div
                        class="h-12 w-12 rounded-full bg-accent text-white flex items-center justify-center font-bold text-lg shadow-sm">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-ink">Multi-Source Ingestion</h3>
                    <p class="text-sm text-muted leading-relaxed">
                        Instantly capture leads via webhooks from Meta Lead Ads, Google Search PPC, 99acres,
                        MagicBricks, and custom website forms.
                    </p>
                </div>

                <!-- Feature 2 -->
                <div
                    class="bg-surface rounded-card border border-border p-6 shadow-sm hover:shadow-md transition-all space-y-4">
                    <div
                        class="h-12 w-12 rounded-full bg-accent text-white flex items-center justify-center font-bold text-lg shadow-sm">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-ink">Credit-Based Distribution</h3>
                    <p class="text-sm text-muted leading-relaxed">
                        Automate fair lead allocation through wallet balances, weighted round-robin distribution, and
                        availability schedules.
                    </p>
                </div>

                <!-- Feature 3 -->
                <div
                    class="bg-surface rounded-card border border-border p-6 shadow-sm hover:shadow-md transition-all space-y-4">
                    <div
                        class="h-12 w-12 rounded-full bg-accent text-white flex items-center justify-center font-bold text-lg shadow-sm">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-ink">SLA Tracking &amp; Escalation</h3>
                    <p class="text-sm text-muted leading-relaxed">
                        Enforce response deadlines. Uncontacted leads automatically trigger notifications or re-route to
                        next available executives.
                    </p>
                </div>

                <!-- Feature 4 -->
                <div
                    class="bg-surface rounded-card border border-border p-6 shadow-sm hover:shadow-md transition-all space-y-4">
                    <div
                        class="h-12 w-12 rounded-full bg-accent text-white flex items-center justify-center font-bold text-lg shadow-sm">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-ink">Replacement Engine</h3>
                    <p class="text-sm text-muted leading-relaxed">
                        Allow executives to flag invalid numbers or uncontactable leads. Automated replacement queues
                        refund spent credits upon verification.
                    </p>
                </div>

                <!-- Feature 5 -->
                <div
                    class="bg-surface rounded-card border border-border p-6 shadow-sm hover:shadow-md transition-all space-y-4">
                    <div
                        class="h-12 w-12 rounded-full bg-accent text-white flex items-center justify-center font-bold text-lg shadow-sm">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-ink">Channel Partner Hierarchy</h3>
                    <p class="text-sm text-muted leading-relaxed">
                        Multi-tenant architecture allowing Builders to manage Channel Partners, Sales Managers, and
                        Account Executives effortlessly.
                    </p>
                </div>

                <!-- Feature 6 -->
                <div
                    class="bg-surface rounded-card border border-border p-6 shadow-sm hover:shadow-md transition-all space-y-4">
                    <div
                        class="h-12 w-12 rounded-full bg-accent text-white flex items-center justify-center font-bold text-lg shadow-sm">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-ink">Real-Time Reporting</h3>
                    <p class="text-sm text-muted leading-relaxed">
                        Track conversion rates by campaign source, monitor team response times, and export accounting
                        reports to CSV/Excel seamlessly.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- 5. "Suitable for Any Kind of Business" Industry Chips -->
    <section class="py-16 bg-surface border-y border-border">
        <div class="max-w-7xl mx-auto px-6 text-center space-y-8">
            <h2 class="text-2xl font-bold text-ink">Tailored for Every Stakeholder in Real Estate</h2>

            <div class="flex flex-wrap items-center justify-center gap-4">
                <span
                    class="px-5 py-2.5 bg-canvas border border-border rounded-pill text-sm font-semibold text-ink shadow-xs hover:border-ink transition cursor-default">
                    🏢 Real Estate Builders &amp; Developers
                </span>
                <span
                    class="px-5 py-2.5 bg-canvas border border-border rounded-pill text-sm font-semibold text-ink shadow-xs hover:border-ink transition cursor-default">
                    🤝 Channel Partners &amp; Agencies
                </span>
                <span
                    class="px-5 py-2.5 bg-canvas border border-border rounded-pill text-sm font-semibold text-ink shadow-xs hover:border-ink transition cursor-default">
                    🎯 Digital Marketing Agencies
                </span>
                <span
                    class="px-5 py-2.5 bg-canvas border border-border rounded-pill text-sm font-semibold text-ink shadow-xs hover:border-ink transition cursor-default">
                    🌐 Property Aggregator Portals
                </span>
            </div>
        </div>
    </section>

    <!-- 6. Product Screenshot Showcase (3 Stacked Panels) -->
    <section id="showcase" class="py-24 bg-canvas space-y-24">
        <div class="max-w-7xl mx-auto px-6 space-y-24">

            <!-- Panel 1: Centralized Dashboard -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <div class="space-y-6">
                    <span class="text-xs font-bold uppercase tracking-wider text-muted">01 / DASHBOARD</span>
                    <h3 class="text-3xl font-bold text-ink">360-Degree Real-Time Command Center</h3>
                    <p class="text-base text-muted leading-relaxed">
                        Monitor incoming lead velocity, wallet balances, site visit schedules, and SLA breach warnings
                        from a single unified view designed specifically for high-density sales operations.
                    </p>
                    <ul class="space-y-3 text-sm font-medium text-ink">
                        <li class="flex items-center space-x-2">
                            <span class="text-success font-bold">✓</span>
                            <span>Live activity feeds and milestone updates</span>
                        </li>
                        <li class="flex items-center space-x-2">
                            <span class="text-success font-bold">✓</span>
                            <span>Tenant-isolated security and data boundary</span>
                        </li>
                    </ul>
                </div>
                <div class="bg-surface rounded-card border border-border p-4 shadow-lg">
                    <div class="bg-canvas rounded-xl p-4 space-y-4 border border-border">
                        <div class="flex justify-between items-center pb-3 border-b border-border">
                            <span class="text-xs font-bold text-ink">CAMPAIGN CONVERSION PERFORMANCE</span>
                            <span class="text-xs text-muted">Last 30 Days</span>
                        </div>
                        <div class="h-44 bg-slate-900 rounded-lg p-4 flex items-end justify-between gap-2">
                            <div class="w-full bg-indigo-500/80 rounded-t h-[60%]"></div>
                            <div class="w-full bg-indigo-500/80 rounded-t h-[85%]"></div>
                            <div class="w-full bg-indigo-500/80 rounded-t h-[45%]"></div>
                            <div class="w-full bg-indigo-500/80 rounded-t h-[95%]"></div>
                            <div class="w-full bg-indigo-500/80 rounded-t h-[70%]"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Panel 2: Lead Kanban Pipeline (Alternating Side) -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center lg:flex-row-reverse">
                <div class="bg-surface rounded-card border border-border p-4 shadow-lg lg:order-2">
                    <div class="space-y-6">
                        <span class="text-xs font-bold uppercase tracking-wider text-muted">02 / PIPELINE</span>
                        <h3 class="text-3xl font-bold text-ink">Kanban Board &amp; Stage Velocity</h3>
                        <p class="text-base text-muted leading-relaxed">
                            Drag and drop leads through stages from New to Site Visit, Negotiation, and Closed Won.
                            Visual SLA countdown timers ensure no lead drops through the cracks.
                        </p>
                        <ul class="space-y-3 text-sm font-medium text-ink">
                            <li class="flex items-center space-x-2">
                                <span class="text-success font-bold">✓</span>
                                <span>Color-coded status badges per stage family</span>
                            </li>
                            <li class="flex items-center space-x-2">
                                <span class="text-success font-bold">✓</span>
                                <span>Instant phone dialer and WhatsApp quick actions</span>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="bg-surface rounded-card border border-border p-4 shadow-lg lg:order-1">
                    <div class="grid grid-cols-3 gap-3 bg-canvas rounded-xl p-3 border border-border">
                        <!-- Col 1 -->
                        <div class="bg-surface p-3 rounded-lg border border-border space-y-2">
                            <div class="text-[10px] font-bold text-muted uppercase">NEW (3)</div>
                            <div class="p-2 bg-canvas rounded border border-border text-xs font-semibold text-ink">Rohan
                                Mehta</div>
                            <div class="p-2 bg-canvas rounded border border-border text-xs font-semibold text-ink">
                                Kavita Shah</div>
                        </div>
                        <!-- Col 2 -->
                        <div class="bg-surface p-3 rounded-lg border border-border space-y-2">
                            <div class="text-[10px] font-bold text-muted uppercase">SITE VISIT (2)</div>
                            <div class="p-2 bg-canvas rounded border border-border text-xs font-semibold text-ink">Aman
                                Gupta</div>
                        </div>
                        <!-- Col 3 -->
                        <div class="bg-surface p-3 rounded-lg border border-border space-y-2">
                            <div class="text-[10px] font-bold text-muted uppercase">BOOKED (4)</div>
                            <div class="p-2 bg-canvas rounded border border-border text-xs font-semibold text-ink">
                                Siddharth R.</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Panel 3: Reports & Excel Export -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <div class="space-y-6">
                    <span class="text-xs font-bold uppercase tracking-wider text-muted">03 / REPORTS &amp; AUDIT</span>
                    <h3 class="text-3xl font-bold text-ink">Automated Accounting &amp; Exports</h3>
                    <p class="text-base text-muted leading-relaxed">
                        Export detailed credit consumption logs, executive performance leaderboards, and campaign ROI
                        data directly to Excel and CSV formats with Maatwebsite/Laravel-Excel integration.
                    </p>
                </div>
                <div class="bg-surface rounded-card border border-border p-6 shadow-lg space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="text-sm font-bold text-ink">EXCEL REPORT GENERATOR</span>
                        <span
                            class="px-2 py-1 bg-green-50 text-green-700 text-xs rounded-pill font-semibold">Ready</span>
                    </div>
                    <div class="p-4 bg-canvas rounded-xl border border-border space-y-2">
                        <div class="flex justify-between text-xs text-muted">
                            <span>Report Type: Lead_Performance_Q3.xlsx</span>
                            <span>1.4 MB</span>
                        </div>
                        <div class="w-full bg-border rounded-full h-2 overflow-hidden">
                            <div class="bg-success h-2 rounded-full w-full"></div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <!-- 7. Final Call to Action (CTA) Band -->
    <section class="py-20 bg-ink text-white">
        <div class="max-w-5xl mx-auto px-6 text-center space-y-8">
            <h2 class="text-4xl sm:text-5xl font-extrabold tracking-tight">
                Start Distributing Smarter Leads Today
            </h2>
            <p class="text-lg text-slate-300 max-w-2xl mx-auto leading-relaxed">
                Join forward-thinking real estate organizations that boost response speed, eliminate dispute overhead,
                and close deals faster.
            </p>
            <div class="pt-4 flex flex-col sm:flex-row justify-center gap-4">
                <a href="{{ route('register') }}"
                    class="bg-white text-ink hover:bg-canvas font-semibold py-4 px-8 rounded-lg text-base shadow-lg transition ease-in-out duration-150">
                    Get Started Free
                </a>
                <a href="{{ route('login') }}"
                    class="border border-slate-700 text-white hover:bg-slate-800 font-semibold py-4 px-8 rounded-lg text-base transition ease-in-out duration-150">
                    Sign In to Portal
                </a>
            </div>
        </div>
    </section>

    <!-- 8. Footer -->
    <footer class="bg-surface border-t border-border py-16 text-xs text-muted">
        <div class="max-w-7xl mx-auto px-6 grid grid-cols-2 md:grid-cols-5 gap-8 mb-12">
            <!-- Brand Column -->
            <div class="col-span-2 space-y-4">
                <div class="flex items-center space-x-3">
                    <div
                        class="h-8 w-8 rounded-xl bg-ink text-white flex items-center justify-center font-bold text-sm">
                        LP
                    </div>
                    <span class="text-base font-bold text-ink">LEAD PANTHER</span>
                </div>
                <p class="text-xs text-muted max-w-sm leading-relaxed">
                    Lead Panther CRM is an enterprise-grade tenant-isolated lead management platform for real estate
                    developers and channel partner networks.
                </p>
            </div>

            <!-- Col 1: Product -->
            <div class="space-y-3">
                <div class="font-bold text-ink uppercase tracking-wider text-[11px]">Product</div>
                <ul class="space-y-2">
                    <li><a href="#features" class="hover:text-ink transition">Multi-Source Ingestion</a></li>
                    <li><a href="#features" class="hover:text-ink transition">Credit Wallet</a></li>
                    <li><a href="#features" class="hover:text-ink transition">SLA Timers</a></li>
                    <li><a href="#features" class="hover:text-ink transition">Replacement Engine</a></li>
                </ul>
            </div>

            <!-- Col 2: Company -->
            <div class="space-y-3">
                <div class="font-bold text-ink uppercase tracking-wider text-[11px]">Company</div>
                <ul class="space-y-2">
                    <li><a href="#" class="hover:text-ink transition">About Us</a></li>
                    <li><a href="#" class="hover:text-ink transition">Careers</a></li>
                    <li><a href="#" class="hover:text-ink transition">Press Kit</a></li>
                    <li><a href="#" class="hover:text-ink transition">Contact</a></li>
                </ul>
            </div>

            <!-- Col 3: Resources & Legal -->
            <div class="space-y-3">
                <div class="font-bold text-ink uppercase tracking-wider text-[11px]">Resources &amp; Legal</div>
                <ul class="space-y-2">
                    <li><a href="{{ route('privacy-policy') }}" class="hover:text-ink transition">Privacy Policy</a></li>
                    <li><a href="{{ route('privacy-policy') }}#meta-lead-ads" class="hover:text-ink transition">Meta Lead Ads Terms</a></li>
                    <li><a href="{{ route('privacy-policy') }}#data-deletion" class="hover:text-ink transition">Data Deletion Instructions</a></li>
                </ul>
            </div>
        </div>

        <div
            class="max-w-7xl mx-auto px-6 border-t border-border pt-8 flex flex-col md:flex-row justify-between items-center gap-4">
            <div>&copy; {{ date('Y') }} Lead Panther CRM. All rights reserved.</div>
            <div class="flex space-x-6 text-muted">
                <a href="{{ route('privacy-policy') }}" class="hover:text-ink transition">Privacy</a>
                <a href="{{ route('privacy-policy') }}#data-security" class="hover:text-ink transition">Security</a>
                <a href="{{ route('privacy-policy') }}#contact" class="hover:text-ink transition">Contact</a>
            </div>
        </div>
    </footer>

</body>

</html>