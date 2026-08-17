@php
    $companyName = config('app.company_name', 'Lead Panther CRM');
    $companyAddress = config('app.company_address', 'Noida');
    $contactEmail = config('app.contact_email', 'faisaluiet@gmail.com');
    $websiteUrl = config('app.url', 'https://darksalmon-cassowary-410169.hostingersite.com/');
    $lastUpdated = 'August 17, 2026';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="index, follow">
    <meta name="description"
        content="Privacy Policy for Lead Panther CRM. Learn how we collect, use, process, and protect personal information, including Meta (Facebook) Lead Ads data integration.">
    <link rel="canonical" href="{{ url('/privacy-policy') }}">
    <title>Privacy Policy | Lead Panther CRM</title>
    <x-ui.vite-assets />
</head>

<body class="bg-canvas text-ink font-sans antialiased selection:bg-accent selection:text-white">

    <!-- 1. Navigation Header -->
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
                <a href="{{ route('landing') }}#features" class="hover:text-ink transition-colors">Features</a>
                <a href="{{ route('landing') }}#how-it-works" class="hover:text-ink transition-colors">How it Works</a>
                <a href="{{ route('landing') }}#pricing" class="hover:text-ink transition-colors">Pricing</a>
                <a href="{{ route('landing') }}#contact" class="hover:text-ink transition-colors">Contact</a>
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

    <!-- 2. Main Content Container -->
    <main class="py-12 md:py-16">
        <div class="max-w-7xl mx-auto px-6">

            <!-- Page Header -->
            <div class="max-w-3xl mb-12">
                <div
                    class="inline-flex items-center space-x-2 px-3 py-1 bg-surface border border-border rounded-pill text-xs font-semibold text-accent shadow-xs mb-4">
                    <span class="w-2 h-2 rounded-full bg-success"></span>
                    <span>Legal &amp; Compliance</span>
                </div>
                <h1 class="text-3xl sm:text-4xl lg:text-5xl font-bold tracking-tight text-ink mb-4">
                    Privacy Policy
                </h1>
                <p class="text-sm text-muted">
                    <strong>Effective &amp; Last Updated:</strong> {{ $lastUpdated }} &bull; Version 2.4
                </p>
                <p class="text-sm text-muted mt-2 leading-relaxed">
                    This Privacy Policy outlines how <strong>{{ $companyName }}</strong> ("Lead Panther CRM", "we",
                    "us", or "our") collects, uses, processes, stores, and protects personal information obtained
                    through our software platform, website ({{ $websiteUrl }}), APIs, and third-party integrations
                    including Meta (Facebook &amp; Instagram) Lead Ads.
                </p>
            </div>

            <!-- Two Column Layout: TOC Sidebar + Policy Text -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-start">

                <!-- Sticky Table of Contents (Desktop) -->
                <aside class="hidden lg:block lg:col-span-4 sticky top-24 space-y-3">
                    <div class="bg-surface rounded-card border border-border p-5 shadow-xs">
                        <div class="text-xs font-bold text-ink uppercase tracking-wider mb-3">
                            Table of Contents
                        </div>
                        <nav class="space-y-1.5 text-xs text-muted">
                            <a href="#intro" class="block hover:text-ink transition py-1">1. Introduction</a>
                            <a href="#info-collect" class="block hover:text-ink transition py-1">2. Information We
                                Collect</a>
                            <a href="#meta-lead-ads"
                                class="block hover:text-ink font-semibold text-primary transition py-1">3. Meta
                                (Facebook) Lead Ads</a>
                            <a href="#how-we-use" class="block hover:text-ink transition py-1">4. How We Use
                                Information</a>
                            <a href="#how-we-share" class="block hover:text-ink transition py-1">5. How We Share
                                Information</a>
                            <a href="#meta-data-policy" class="block hover:text-ink transition py-1">6. Meta Platform
                                Data Handling</a>
                            <a href="#data-retention" class="block hover:text-ink transition py-1">7. Data Retention</a>
                            <a href="#data-security" class="block hover:text-ink transition py-1">8. Data Security</a>
                            <a href="#cookies" class="block hover:text-ink transition py-1">9. Cookies and Tracking</a>
                            <a href="#third-parties" class="block hover:text-ink transition py-1">10. Third-Party
                                Services</a>
                            <a href="#user-rights" class="block hover:text-ink transition py-1">11. Your Privacy
                                Rights</a>
                            <a href="#data-deletion"
                                class="block hover:text-ink font-semibold text-primary transition py-1">12. Data
                                Deletion Instructions</a>
                            <a href="#children" class="block hover:text-ink transition py-1">13. Children's Privacy</a>
                            <a href="#transfers" class="block hover:text-ink transition py-1">14. International Data
                                Transfers</a>
                            <a href="#changes" class="block hover:text-ink transition py-1">15. Changes to this
                                Policy</a>
                            <a href="#contact" class="block hover:text-ink transition py-1">16. Contact Information</a>
                        </nav>
                    </div>

                    <!-- Meta Compliance Card -->
                    <div class="bg-canvas border border-border rounded-card p-4 text-xs space-y-2">
                        <div class="font-bold text-ink flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-primary" fill="currentColor" viewBox="0 0 24 24">
                                <path
                                    d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                            </svg>
                            <span>Meta Platform Compliance</span>
                        </div>
                        <p class="text-muted leading-relaxed text-[11px]">
                            Lead Panther CRM complies with the Meta Platform Terms and Developer Policies. We never
                            sell, lease, or monetize user data received from Meta APIs.
                        </p>
                    </div>
                </aside>

                <!-- Document Body (Col 8) -->
                <div class="lg:col-span-8 space-y-10 text-xs sm:text-sm text-ink/90 leading-relaxed">

                    <!-- Section 1: Introduction -->
                    <section id="intro"
                        class="bg-surface rounded-card border border-border p-6 sm:p-8 shadow-xs space-y-3 scroll-mt-24">
                        <h2 class="text-lg sm:text-xl font-bold tracking-tight text-ink flex items-center gap-2">
                            <span
                                class="text-xs px-2 py-0.5 rounded-pill bg-canvas border border-border text-muted font-mono">01</span>
                            <span>Introduction</span>
                        </h2>
                        <p>
                            <strong>Lead Panther CRM</strong> is an enterprise customer relationship management software
                            platform designed for real estate developers, channel partners, and authorized sales teams
                            to manage multi-source lead capture, credit-based distribution, SLA response timers, and
                            sales pipelines.
                        </p>
                        <p>
                            This Privacy Policy describes our practices concerning the collection, use, maintenance,
                            processing, and disclosure of personal and business information when you use our web
                            application, access our services, or when your information is submitted to our CRM through
                            connected integration channels.
                        </p>
                    </section>

                    <!-- Section 2: Information We Collect -->
                    <section id="info-collect"
                        class="bg-surface rounded-card border border-border p-6 sm:p-8 shadow-xs space-y-4 scroll-mt-24">
                        <h2 class="text-lg sm:text-xl font-bold tracking-tight text-ink flex items-center gap-2">
                            <span
                                class="text-xs px-2 py-0.5 rounded-pill bg-canvas border border-border text-muted font-mono">02</span>
                            <span>Information We Collect</span>
                        </h2>
                        <p>We may collect and process the following categories of information:</p>
                        <ul class="list-disc pl-5 space-y-2">
                            <li><strong>Direct Account &amp; Profile Data:</strong> Name, professional email address,
                                mobile phone number, job role, organization name, and authentication credentials when
                                creating or accessing a CRM user account.</li>
                            <li><strong>Lead Information Submitted via Forms:</strong> Full name, email address, phone
                                number, city, location preferences, budget criteria, property requirements, and
                                responses provided to custom questions submitted via landing pages, digital ad
                                campaigns, or inquiry forms.</li>
                            <li><strong>Third-Party Integration Data:</strong> Information received automatically
                                through connected advertising and marketing platforms, such as <strong>Meta (Facebook
                                    &amp; Instagram) Lead Ads</strong>, Google Lead Form Ads, and authorized property
                                portals.</li>
                            <li><strong>Technical &amp; Log Data:</strong> Internet Protocol (IP) address, browser type
                                and version, operating system, device identifiers, time-zone settings, audit logs, and
                                interaction timestamps recorded for diagnostic and security purposes.</li>
                            <li><strong>Communication Records:</strong> Notes, call outcome statuses, WhatsApp/SMS
                                activity logs, and follow-up reminders recorded by authorized sales executives within
                                the platform.</li>
                        </ul>
                    </section>

                    <!-- Section 3: Meta / Facebook Lead Ads -->
                    <section id="meta-lead-ads"
                        class="bg-surface rounded-card border border-primary/20 bg-primary/[0.01] p-6 sm:p-8 shadow-xs space-y-4 scroll-mt-24">
                        <div class="flex items-center gap-2">
                            <span
                                class="text-xs px-2 py-0.5 rounded-pill bg-primary/10 border border-primary/20 text-primary font-mono font-bold">03</span>
                            <h2 class="text-lg sm:text-xl font-bold tracking-tight text-ink">Meta (Facebook &amp;
                                Instagram) Lead Ads Integration</h2>
                        </div>

                        <p>
                            Lead Panther CRM provides an optional direct integration with the <strong>Meta Graph API /
                                Webhooks</strong> to allow subscribing businesses to automatically receive lead
                            inquiries generated from their Facebook and Instagram Lead Ad campaigns.
                        </p>

                        <div class="p-4 bg-canvas rounded-xl border border-border space-y-2">
                            <h3 class="font-bold text-ink text-xs uppercase tracking-wider">How Meta Data is Processed:
                            </h3>
                            <ul class="list-disc pl-5 space-y-1.5 text-xs">
                                <li><strong>Inbound Retrieval:</strong> When an individual submits a Meta Instant Form,
                                    Meta transmits an encrypted webhook notification to Lead Panther CRM. Our system
                                    queries the Meta Graph API using the business's secure Page Access Token to retrieve
                                    the submitted lead details (e.g., name, phone, email, and form custom answers).</li>
                                <li><strong>Authorized Business Use Only:</strong> Data retrieved from Meta Lead Ads is
                                    used exclusively for populating the subscribing business's private CRM database,
                                    routing the inquiry to assigned sales agents, and facilitating direct sales
                                    communication.</li>
                                <li><strong>Customer Responsibility:</strong> Businesses connecting their Meta accounts
                                    to Lead Panther CRM represent and warrant that they possess all required consents,
                                    legal bases, and privacy notices necessary under applicable data protection laws.
                                </li>
                                <li><strong>No Data Brokering:</strong> <strong>Lead Panther CRM does not sell, lease,
                                        rent, or trade lead data obtained from Meta Lead Ads to any third
                                        party.</strong></li>
                            </ul>
                        </div>
                    </section>

                    <!-- Section 4: How We Use Information -->
                    <section id="how-we-use"
                        class="bg-surface rounded-card border border-border p-6 sm:p-8 shadow-xs space-y-3 scroll-mt-24">
                        <h2 class="text-lg sm:text-xl font-bold tracking-tight text-ink flex items-center gap-2">
                            <span
                                class="text-xs px-2 py-0.5 rounded-pill bg-canvas border border-border text-muted font-mono">04</span>
                            <span>How We Use Information</span>
                        </h2>
                        <p>We process collected information for the following legitimate business purposes:</p>
                        <ul class="list-disc pl-5 space-y-2">
                            <li>To create, organize, and manage CRM lead records within tenant-isolated databases.</li>
                            <li>To enable authorized sales agents and account managers to contact prospective buyers
                                regarding their inquiries.</li>
                            <li>To operate, maintain, and provide core CRM capabilities including SLA timers, automated
                                lead distribution, and analytics.</li>
                            <li>To authenticate user sessions, manage account roles, and enforce granular access
                                control.</li>
                            <li>To provide customer support, troubleshoot system errors, and process service requests.
                            </li>
                            <li>To prevent fraudulent access, unauthorized intrusions, and security vulnerabilities.
                            </li>
                            <li>To maintain active API connections and webhook subscriptions with integrated third-party
                                platforms.</li>
                            <li>To comply with applicable legal obligations, statutory reporting, and dispute
                                resolutions.</li>
                        </ul>
                    </section>

                    <!-- Section 5: How We Share Information -->
                    <section id="how-we-share"
                        class="bg-surface rounded-card border border-border p-6 sm:p-8 shadow-xs space-y-3 scroll-mt-24">
                        <h2 class="text-lg sm:text-xl font-bold tracking-tight text-ink flex items-center gap-2">
                            <span
                                class="text-xs px-2 py-0.5 rounded-pill bg-canvas border border-border text-muted font-mono">05</span>
                            <span>How We Share Information</span>
                        </h2>
                        <p>
                            We do not sell personal information. We may disclose personal and lead data only under the
                            following strictly limited circumstances:
                        </p>
                        <ul class="list-disc pl-5 space-y-2">
                            <li><strong>Authorized Organization Users:</strong> Lead information is accessible only to
                                authenticated users (e.g., builders, channel partners, and sales executives) who have
                                been granted explicit permissions within the subscribing tenant organization.</li>
                            <li><strong>Infrastructure &amp; Hosting Providers:</strong> Trusted third-party hosting,
                                database, and cloud infrastructure service providers who operate under strict
                                confidentiality agreements.</li>
                            <li><strong>Third-Party Integration Providers:</strong> In accordance with user-configured
                                integrations (such as Meta Graph API or Google Ads API) solely to execute requested
                                synchronizations.</li>
                            <li><strong>Legal &amp; Statutory Disclosures:</strong> When required by law, subpoena,
                                court order, or governmental authority to protect legal rights or public safety.</li>
                        </ul>
                    </section>

                    <!-- Section 6: Meta Platform Data Handling -->
                    <section id="meta-data-policy"
                        class="bg-surface rounded-card border border-border p-6 sm:p-8 shadow-xs space-y-3 scroll-mt-24">
                        <h2 class="text-lg sm:text-xl font-bold tracking-tight text-ink flex items-center gap-2">
                            <span
                                class="text-xs px-2 py-0.5 rounded-pill bg-canvas border border-border text-muted font-mono">06</span>
                            <span>Meta Platform Data Handling Compliance</span>
                        </h2>
                        <p>
                            In compliance with <strong>Meta Platform Terms</strong> and <strong>Meta Developer
                                Policies</strong>:
                        </p>
                        <ul class="list-disc pl-5 space-y-1.5">
                            <li>All access tokens, app secrets, and webhook verify tokens received from Meta are
                                encrypted at rest using AES-256 encryption.</li>
                            <li>Meta user data is processed solely on behalf of the customer who authorized the Meta
                                integration and is never repurposed for profiling, advertising networks, or independent
                                commercial use.</li>
                            <li>We promptly update and delete cached Meta data in accordance with Meta's developer
                                requirements and platform guidelines.</li>
                        </ul>
                    </section>

                    <!-- Section 7: Data Retention -->
                    <section id="data-retention"
                        class="bg-surface rounded-card border border-border p-6 sm:p-8 shadow-xs space-y-3 scroll-mt-24">
                        <h2 class="text-lg sm:text-xl font-bold tracking-tight text-ink flex items-center gap-2">
                            <span
                                class="text-xs px-2 py-0.5 rounded-pill bg-canvas border border-border text-muted font-mono">07</span>
                            <span>Data Retention</span>
                        </h2>
                        <p>
                            We retain personal data and lead records only for the duration necessary to fulfill the
                            purposes described in this Privacy Policy, satisfy active customer subscription agreements,
                            adhere to statutory retention requirements, and resolve legal disputes. When data is no
                            longer required, it is securely deleted or anonymized.
                        </p>
                    </section>

                    <!-- Section 8: Data Security -->
                    <section id="data-security"
                        class="bg-surface rounded-card border border-border p-6 sm:p-8 shadow-xs space-y-3 scroll-mt-24">
                        <h2 class="text-lg sm:text-xl font-bold tracking-tight text-ink flex items-center gap-2">
                            <span
                                class="text-xs px-2 py-0.5 rounded-pill bg-canvas border border-border text-muted font-mono">08</span>
                            <span>Data Security Measures</span>
                        </h2>
                        <p>
                            We implement commercially reasonable technical and organizational security controls designed
                            to safeguard personal data against unauthorized access, destruction, loss, or alteration:
                        </p>
                        <ul class="list-disc pl-5 space-y-2">
                            <li><strong>Encryption in Transit:</strong> Strict HTTPS/TLS 1.3 encryption across all
                                public and API endpoints.</li>
                            <li><strong>Encryption at Rest:</strong> AES-256 encryption for sensitive API keys, system
                                tokens, and integration secrets.</li>
                            <li><strong>Role-Based Access Control:</strong> Strict permission enforcement and
                                tenant-scoped isolation ensuring users can access only authorized leads.</li>
                            <li><strong>Audit Logging:</strong> Comprehensive immutable logging of authentication
                                attempts, lead state mutations, and administrative impersonations.</li>
                        </ul>
                    </section>

                    <!-- Section 9: Cookies and Tracking -->
                    <section id="cookies"
                        class="bg-surface rounded-card border border-border p-6 sm:p-8 shadow-xs space-y-3 scroll-mt-24">
                        <h2 class="text-lg sm:text-xl font-bold tracking-tight text-ink flex items-center gap-2">
                            <span
                                class="text-xs px-2 py-0.5 rounded-pill bg-canvas border border-border text-muted font-mono">09</span>
                            <span>Cookies and Session Technologies</span>
                        </h2>
                        <p>
                            Lead Panther CRM utilizes essential HTTP session cookies, CSRF protection tokens, and
                            authentication cookies necessary for application security, user authentication state, and
                            session preservation. We do not utilize third-party cross-site advertising tracker cookies
                            on our application interface.
                        </p>
                    </section>

                    <!-- Section 10: Third-Party Services -->
                    <section id="third-parties"
                        class="bg-surface rounded-card border border-border p-6 sm:p-8 shadow-xs space-y-3 scroll-mt-24">
                        <h2 class="text-lg sm:text-xl font-bold tracking-tight text-ink flex items-center gap-2">
                            <span
                                class="text-xs px-2 py-0.5 rounded-pill bg-canvas border border-border text-muted font-mono">10</span>
                            <span>Third-Party Services &amp; Links</span>
                        </h2>
                        <p>
                            Our platform allows integration with third-party platforms (including Meta Ads, Google Ads,
                            and property portals). These independent third parties have their own distinct privacy
                            policies and terms of service. We encourage users to review the privacy notices of any
                            third-party service they connect with.
                        </p>
                    </section>

                    <!-- Section 11: User Rights -->
                    <section id="user-rights"
                        class="bg-surface rounded-card border border-border p-6 sm:p-8 shadow-xs space-y-3 scroll-mt-24">
                        <h2 class="text-lg sm:text-xl font-bold tracking-tight text-ink flex items-center gap-2">
                            <span
                                class="text-xs px-2 py-0.5 rounded-pill bg-canvas border border-border text-muted font-mono">11</span>
                            <span>Your Privacy Rights</span>
                        </h2>
                        <p>Depending on your jurisdiction and applicable data protection legislation, you may hold
                            rights to:</p>
                        <ul class="list-disc pl-5 space-y-1.5">
                            <li><strong>Access:</strong> Request confirmation and a copy of personal information held
                                about you.</li>
                            <li><strong>Correction:</strong> Request rectification of inaccurate or incomplete records.
                            </li>
                            <li><strong>Deletion (Erasure):</strong> Request the permanent removal of your personal
                                information.</li>
                            <li><strong>Restriction &amp; Objection:</strong> Request limits on or object to specific
                                processing activities.</li>
                            <li><strong>Withdrawal of Consent:</strong> Revoke consent previously provided for
                                communication.</li>
                        </ul>
                    </section>

                    <!-- Section 12: Lead Data Deletion Instructions -->
                    <section id="data-deletion"
                        class="bg-surface rounded-card border border-border p-6 sm:p-8 shadow-xs space-y-4 scroll-mt-24">
                        <div class="flex items-center gap-2">
                            <span
                                class="text-xs px-2 py-0.5 rounded-pill bg-canvas border border-border text-muted font-mono font-bold">12</span>
                            <h2 class="text-lg sm:text-xl font-bold tracking-tight text-ink">User Data Deletion Instructions</h2>
                        </div>
                        <p>
                            If you have submitted your contact information through a Lead Panther CRM form, or via a
                            connected <strong>Meta (Facebook/Instagram) Lead Ad</strong>, and wish to have your personal
                            data permanently removed from our systems, you may submit a deletion request at any time.
                        </p>

                        <div class="p-4 bg-canvas rounded-xl border border-border space-y-3">
                            <div class="font-bold text-ink text-xs uppercase tracking-wider">How to Request Data
                                Deletion:</div>
                            <ol class="list-decimal pl-5 space-y-2 text-xs">
                                <li>Send an email to our Data Protection Officer at: <a
                                        href="mailto:{{ $contactEmail }}"
                                        class="font-mono font-bold text-primary hover:underline">{{ $contactEmail }}</a>
                                </li>
                                <li>Use the subject line: <code
                                        class="bg-surface px-1.5 py-0.5 rounded border border-border text-ink font-bold">Data Deletion Request - Lead Panther CRM</code>
                                </li>
                                <li>Provide your full name, email address, and mobile phone number that was used when
                                    submitting the inquiry so we can accurately locate your record.</li>
                                <li>Upon identity verification, we will permanently purge your personal information from
                                    our active databases within thirty (30) days and notify the associated business
                                    account.</li>
                            </ol>
                        </div>
                    </section>

                    <!-- Section 13: Children's Privacy -->
                    <section id="children"
                        class="bg-surface rounded-card border border-border p-6 sm:p-8 shadow-xs space-y-3 scroll-mt-24">
                        <h2 class="text-lg sm:text-xl font-bold tracking-tight text-ink flex items-center gap-2">
                            <span
                                class="text-xs px-2 py-0.5 rounded-pill bg-canvas border border-border text-muted font-mono">13</span>
                            <span>Children's Privacy</span>
                        </h2>
                        <p>
                            Lead Panther CRM is an enterprise business platform and is not directed to individuals under
                            the age of 18. We do not knowingly collect or solicit personal information from children. If
                            we discover that a child has provided us with personal information, we will delete it
                            promptly.
                        </p>
                    </section>

                    <!-- Section 14: International Transfers -->
                    <section id="transfers"
                        class="bg-surface rounded-card border border-border p-6 sm:p-8 shadow-xs space-y-3 scroll-mt-24">
                        <h2 class="text-lg sm:text-xl font-bold tracking-tight text-ink flex items-center gap-2">
                            <span
                                class="text-xs px-2 py-0.5 rounded-pill bg-canvas border border-border text-muted font-mono">14</span>
                            <span>International Data Transfers</span>
                        </h2>
                        <p>
                            Information collected through Lead Panther CRM may be stored and processed in data centers
                            located in regions where our cloud hosting providers maintain facilities, subject to
                            appropriate technical safeguards and data transfer mechanisms required by law.
                        </p>
                    </section>

                    <!-- Section 15: Changes to Policy -->
                    <section id="changes"
                        class="bg-surface rounded-card border border-border p-6 sm:p-8 shadow-xs space-y-3 scroll-mt-24">
                        <h2 class="text-lg sm:text-xl font-bold tracking-tight text-ink flex items-center gap-2">
                            <span
                                class="text-xs px-2 py-0.5 rounded-pill bg-canvas border border-border text-muted font-mono">15</span>
                            <span>Changes to this Privacy Policy</span>
                        </h2>
                        <p>
                            We may update this Privacy Policy periodically to reflect enhancements to our platform,
                            third-party integration guidelines, or statutory requirements. Any revisions will be
                            published directly on this page with an updated "Last Updated" timestamp.
                        </p>
                    </section>

                    <!-- Section 16: Contact Us -->
                    <section id="contact"
                        class="bg-surface rounded-card border border-border p-6 sm:p-8 shadow-xs space-y-4 scroll-mt-24">
                        <h2 class="text-lg sm:text-xl font-bold tracking-tight text-ink flex items-center gap-2">
                            <span
                                class="text-xs px-2 py-0.5 rounded-pill bg-canvas border border-border text-muted font-mono">16</span>
                            <span>Contact Information</span>
                        </h2>
                        <p>For any questions, compliance inquiries, or data privacy requests regarding this policy,
                            please contact us:</p>

                        <div class="p-4 bg-canvas rounded-xl border border-border text-xs space-y-2">
                            <div><strong>Entity Name:</strong> {{ $companyName }}</div>
                            <div><strong>Address:</strong> {{ $companyAddress }}</div>
                            <div><strong>Privacy Contact Email:</strong> <a href="mailto:{{ $contactEmail }}"
                                    class="text-primary font-mono hover:underline">{{ $contactEmail }}</a></div>
                            <div><strong>Official Website:</strong> <a href="{{ $websiteUrl }}"
                                    class="text-primary font-mono hover:underline" target="_blank"
                                    rel="noopener">{{ $websiteUrl }}</a></div>
                        </div>
                    </section>

                </div>
            </div>
        </div>
    </main>

    <!-- 3. Footer -->
    <footer class="bg-surface border-t border-border py-12 text-xs text-muted mt-16">
        <div class="max-w-7xl mx-auto px-6 grid grid-cols-1 md:grid-cols-4 gap-8 mb-12">
            <!-- Brand Column -->
            <div class="space-y-4">
                <a href="{{ route('landing') }}" class="flex items-center space-x-3">
                    <div
                        class="h-8 w-8 rounded-lg bg-ink text-white flex items-center justify-center font-bold text-sm">
                        LP
                    </div>
                    <span class="text-base font-bold text-ink">LEAD PANTHER</span>
                </a>
                <p class="text-xs text-muted max-w-sm leading-relaxed">
                    Lead Panther CRM is an enterprise-grade lead management platform for real estate developers and
                    channel partner networks.
                </p>
            </div>

            <!-- Col 1: Product -->
            <div class="space-y-3">
                <div class="font-bold text-ink uppercase tracking-wider text-[11px]">Product</div>
                <ul class="space-y-2">
                    <li><a href="{{ route('landing') }}#features" class="hover:text-ink transition">Multi-Source
                            Ingestion</a></li>
                    <li><a href="{{ route('landing') }}#features" class="hover:text-ink transition">Credit Wallet</a>
                    </li>
                    <li><a href="{{ route('landing') }}#features" class="hover:text-ink transition">SLA Timers</a></li>
                    <li><a href="{{ route('landing') }}#features" class="hover:text-ink transition">Replacement
                            Engine</a></li>
                </ul>
            </div>

            <!-- Col 2: Company -->
            <div class="space-y-3">
                <div class="font-bold text-ink uppercase tracking-wider text-[11px]">Company</div>
                <ul class="space-y-2">
                    <li><a href="{{ route('landing') }}" class="hover:text-ink transition">About Us</a></li>
                    <li><a href="{{ route('landing') }}#contact" class="hover:text-ink transition">Contact</a></li>
                </ul>
            </div>

            <!-- Col 3: Legal -->
            <div class="space-y-3">
                <div class="font-bold text-ink uppercase tracking-wider text-[11px]">Legal &amp; Compliance</div>
                <ul class="space-y-2">
                    <li><a href="{{ route('privacy-policy') }}"
                            class="font-bold text-ink hover:underline transition">Privacy Policy</a></li>
                    <li><a href="{{ route('privacy-policy') }}#data-deletion" class="hover:text-ink transition">Data
                            Deletion Instructions</a></li>
                </ul>
            </div>
        </div>

        <div
            class="max-w-7xl mx-auto px-6 border-t border-border pt-8 flex flex-col md:flex-row justify-between items-center gap-4">
            <div>&copy; {{ date('Y') }} Lead Panther CRM. All rights reserved.</div>
            <div class="flex space-x-6 text-muted">
                <a href="{{ route('privacy-policy') }}" class="hover:text-ink transition font-medium">Privacy Policy</a>
                <a href="{{ route('privacy-policy') }}#data-security" class="hover:text-ink transition">Security</a>
                <a href="{{ route('privacy-policy') }}#contact" class="hover:text-ink transition">Contact</a>
            </div>
        </div>
    </footer>

</body>

</html>