<div class="space-y-6" x-data="{
    copied: false,
    showMetaToken: false,
    showMetaSecret: false,
    showGitToken: false,
}">
    <!-- Breadcrumbs -->
    <div class="flex items-center gap-2 text-xs text-muted">
        <a href="{{ url('/settings/org') }}" class="hover:text-ink transition">Settings</a>
        <span>&gt;</span>
        <span class="text-ink font-semibold">Integrations</span>
    </div>

    <!-- Page Title & Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-ink">API &amp; Webhook Integrations</h1>
            <p class="text-xs text-muted">Configure Meta Ads and Git credentials (Encrypted at rest).</p>
        </div>
    </div>

    <!-- Top Two Tabs: Meta Ads (Facebook) & Git Repository -->
    <div class="flex items-center gap-2.5">
        <!-- Meta Ads Tab -->
        <button
            type="button"
            wire:click="switchTab('meta')"
            class="flex items-center gap-2.5 px-4 py-2 rounded-xl text-xs font-bold transition cursor-pointer {{ $activeTab === 'meta' ? 'bg-white border-2 border-primary text-primary shadow-xs' : 'bg-white border border-border text-ink/70 hover:bg-canvas' }}"
        >
            <span class="w-5 h-5 rounded-full bg-[#1877F2] text-white flex items-center justify-center font-bold text-xs shrink-0">f</span>
            <span>Meta Ads (Facebook)</span>
        </button>

        <!-- Git Repository Tab -->
        <button
            type="button"
            wire:click="switchTab('git')"
            class="flex items-center gap-2.5 px-4 py-2 rounded-xl text-xs font-bold transition cursor-pointer {{ $activeTab === 'git' ? 'bg-white border-2 border-primary text-primary shadow-xs' : 'bg-white border border-border text-ink/70 hover:bg-canvas' }}"
        >
            <span class="px-1.5 py-0.5 rounded bg-ink text-white font-mono text-[9px] font-extrabold uppercase shrink-0">GIT</span>
            <span>Git Repository</span>
        </button>
    </div>

    <!-- Single Configuration Form Card -->
    <div class="bg-surface rounded-2xl border border-border p-6 shadow-xs space-y-6">

        <!-- Hidden inputs to prevent aggressive browser autofill -->
        <input type="text" class="hidden" name="prevent_autofill_user" style="display:none !important;" aria-hidden="true" tabindex="-1" autocomplete="off" />
        <input type="password" class="hidden" name="prevent_autofill_pass" style="display:none !important;" aria-hidden="true" tabindex="-1" autocomplete="new-password" />

        <!-- Card Header with Brand Icon, Title, Status, and Actions -->
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 pb-4 border-b border-border">
            <div class="flex items-start gap-3.5">
                @if($activeTab === 'meta')
                    <div>
                        <h2 class="text-base font-bold text-ink">
                            Meta Ads (Facebook &amp; Instagram Lead Ads)
                        </h2>
                        <p class="text-xs text-muted mt-0.5">
                            Connect your Facebook &amp; Instagram Lead Ads account to sync leads directly to CRM.
                        </p>
                    </div>
                @else
                    <div>
                        <h2 class="text-base font-bold text-ink">
                            Git Repository Integration
                        </h2>
                        <p class="text-xs text-muted mt-0.5">
                            Connect your Git repository (GitHub/GitLab) to sync and deploy codebase updates directly.
                        </p>
                    </div>
                @endif
            </div>

            <!-- Status Pill, Last Synced & Actions -->
            @php
                $isMeta = ($activeTab === 'meta');
                $healthStatus = $isMeta ? $metaHealthStatus : $gitHealthStatus;
                $lastSynced = $isMeta ? $metaLastSyncedTime : $gitLastSyncedTime;
                $isConnected = ($healthStatus === 'healthy');
                $isError = ($healthStatus === 'error');
            @endphp
            <div class="flex flex-col sm:items-end gap-1.5 shrink-0">
                <div class="flex items-center gap-2">
                    @if($isConnected)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                            <span>Connected</span>
                        </span>
                    @elseif($isError)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200">
                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                            <span>Connection Error</span>
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-neutral-100 text-neutral-600 border border-neutral-200">
                            <span class="w-1.5 h-1.5 rounded-full bg-neutral-400"></span>
                            <span>Not Connected</span>
                        </span>
                    @endif

                    <button
                        type="button"
                        wire:click="testCurrentConnection"
                        wire:loading.attr="disabled"
                        class="px-3 py-1 rounded-xl border border-border bg-white text-xs font-semibold text-ink hover:bg-canvas transition flex items-center gap-1 shadow-2xs cursor-pointer disabled:opacity-60"
                        title="Verify connection"
                    >
                        <svg wire:loading.remove wire:target="testCurrentConnection" class="w-3.5 h-3.5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        <svg wire:loading wire:target="testCurrentConnection" class="animate-spin w-3.5 h-3.5 text-ink" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>Reconnect</span>
                    </button>

                    @if(($isMeta && $selectedAccountId) || (!$isMeta && $gitAccountId))
                        <button
                            type="button"
                            wire:click="disconnectCurrentIntegration"
                            wire:confirm="Are you sure you want to disconnect this integration? Existing stored credentials will be removed."
                            class="px-2.5 py-1 rounded-xl border border-rose-200 bg-rose-50 text-xs font-semibold text-rose-600 hover:bg-rose-100 transition cursor-pointer"
                            title="Disconnect"
                        >
                            <span>Disconnect</span>
                        </button>
                    @endif
                </div>

                @if($lastSynced)
                    <div class="text-[11px] text-muted">
                        Last synced: {{ $lastSynced }}
                    </div>
                @endif
            </div>
        </div>

        <!-- FORM: META ADS (FACEBOOK) -->
        @if($activeTab === 'meta')
            <div class="space-y-4 text-xs">
                <!-- Row 1: Account Display Name & Facebook Page ID -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="font-bold text-ink">Account Display Name <span class="text-rose-500">*</span></label>
                        <input
                            type="text"
                            wire:model="accountName"
                            value="{{ $accountName }}"
                            name="account_display_name"
                            autocomplete="off"
                            placeholder="Meta Ads Main Account"
                            class="w-full h-9 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:outline-none focus:ring-1 focus:ring-ink transition mt-1"
                        />
                        @error('accountName') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="font-bold text-ink">Facebook Page ID <span class="text-rose-500">*</span></label>
                        <input
                            type="text"
                            wire:model="metaPageId"
                            value="{{ $metaPageId }}"
                            name="meta_page_id"
                            autocomplete="off"
                            placeholder="e.g. 109283746501928"
                            class="w-full h-9 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs font-mono focus:outline-none focus:ring-1 focus:ring-ink transition mt-1"
                        />
                        @error('metaPageId') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                        <p class="text-[11px] text-muted mt-1">Found in your Meta Business Suite &gt; Page About info.</p>
                    </div>
                </div>

                <!-- Row 2: Meta App ID & Meta App Secret -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="font-bold text-ink">Meta App ID <span class="text-muted font-normal">(Optional)</span></label>
                        <input
                            type="text"
                            wire:model="metaAppId"
                            value="{{ $metaAppId }}"
                            name="meta_app_id"
                            autocomplete="off"
                            placeholder="e.g. 987654321098765"
                            class="w-full h-9 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs font-mono focus:outline-none focus:ring-1 focus:ring-ink transition mt-1"
                        />
                        @error('metaAppId') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                        <p class="text-[11px] text-muted mt-1">Found in Meta for Developers app dashboard.</p>
                    </div>

                    <div>
                        <label class="font-bold text-ink">Meta App Secret <span class="text-muted font-normal">(Optional)</span></label>
                        <div class="relative mt-1">
                            <input
                                :type="showMetaSecret ? 'text' : 'password'"
                                wire:model="metaAppSecret"
                                value="{{ $metaAppSecret }}"
                                name="meta_app_secret"
                                autocomplete="new-password"
                                placeholder="••••••••••••••••"
                                class="w-full h-9 pl-3.5 pr-10 rounded-lg border border-border bg-canvas text-ink text-xs font-mono focus:outline-none focus:ring-1 focus:ring-ink transition"
                            />
                            <button
                                type="button"
                                x-on:click="showMetaSecret = !showMetaSecret"
                                tabindex="-1"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted hover:text-ink cursor-pointer"
                            >
                                <svg x-show="!showMetaSecret" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                <svg x-show="showMetaSecret" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:none;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"></path></svg>
                            </button>
                        </div>
                        @error('metaAppSecret') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                        <p class="text-[11px] text-muted mt-1">Used for webhook payload HMAC-SHA256 signature verification.</p>
                    </div>
                </div>

                <!-- Row 3: Page Access Token (Full Width) -->
                <div>
                    <div class="flex items-center justify-between">
                        <label class="font-bold text-ink">Page Access Token <span class="text-rose-500">*</span></label>
                        <a href="https://developers.facebook.com/docs/pages/access-tokens" target="_blank" rel="noopener noreferrer" class="text-[11px] text-primary hover:underline inline-flex items-center gap-1">
                            <span>System User Token Guide</span>
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"></circle><line x1="12" y1="16" x2="12" y2="12" stroke-width="2"></line><line x1="12" y1="8" x2="12.01" y2="8" stroke-width="2"></line></svg>
                        </a>
                    </div>
                    <div class="relative mt-1">
                        <input
                            :type="showMetaToken ? 'text' : 'password'"
                            wire:model="metaAccessToken"
                            value="{{ $metaAccessToken }}"
                            name="meta_access_token"
                            autocomplete="new-password"
                            placeholder="EAABw... (Never-expiring System User Token or Graph Token)"
                            class="w-full h-9 pl-3.5 pr-10 rounded-lg border border-border bg-canvas text-ink text-xs font-mono focus:outline-none focus:ring-1 focus:ring-ink transition"
                        />
                        <button
                            type="button"
                            x-on:click="showMetaToken = !showMetaToken"
                            tabindex="-1"
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted hover:text-ink cursor-pointer"
                        >
                            <svg x-show="!showMetaToken" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                            <svg x-show="showMetaToken" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:none;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"></path></svg>
                        </button>
                    </div>
                    @error('metaAccessToken') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                    <p class="text-[11px] text-muted mt-1">Requires <code class="font-bold text-ink">leads_retrieval</code> and <code class="font-bold text-ink">pages_show_list</code> permissions.</p>
                </div>

                <!-- Row 4: Webhook Verify Token & Callback URL -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="font-bold text-ink">Webhook Verify Token</label>
                        <div class="flex items-center space-x-2 mt-1">
                            <input
                                type="text"
                                wire:model="metaVerifyToken"
                                value="{{ $metaVerifyToken }}"
                                name="meta_verify_token"
                                autocomplete="off"
                                placeholder="Random verification token"
                                class="w-full h-9 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs font-mono focus:outline-none focus:ring-1 focus:ring-ink transition"
                            />
                            <button
                                type="button"
                                wire:click="regenerateVerifyToken"
                                class="px-3.5 h-9 bg-white border border-border rounded-xl text-ink font-semibold text-xs hover:bg-canvas transition flex items-center gap-1.5 shrink-0 shadow-2xs cursor-pointer"
                            >
                                <svg class="w-3.5 h-3.5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                <span>Generate New</span>
                            </button>
                        </div>
                        <p class="text-[11px] text-muted mt-1">Paste this into Meta App Dashboard &gt; Webhooks &gt; Verify Token.</p>
                    </div>

                    <div>
                        <label class="font-bold text-ink">Webhook Callback URL</label>
                        <div class="flex items-center space-x-2 mt-1">
                            <input
                                type="text"
                                readonly
                                value="{{ url('/api/webhooks/meta/' . ($selectedAccountId ?: '{portal_account_id}')) }}"
                                id="metaCallbackUrl"
                                class="w-full h-9 px-3.5 rounded-lg border border-border bg-canvas/60 text-muted font-mono text-[11px] select-all cursor-text focus:outline-none"
                            />
                            <button
                                type="button"
                                x-on:click="
                                    navigator.clipboard.writeText(document.getElementById('metaCallbackUrl').value);
                                    copied = true;
                                    setTimeout(function() { copied = false; }, 2000);
                                "
                                class="px-3.5 h-9 bg-white border border-border rounded-xl text-ink font-semibold text-xs hover:bg-canvas transition flex items-center gap-1.5 shrink-0 shadow-2xs cursor-pointer"
                            >
                                <span x-show="!copied" class="flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"></path></svg>
                                    <span>Copy</span>
                                </span>
                                <span x-show="copied" class="text-emerald-700 font-bold flex items-center gap-1" style="display: none;">
                                    <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                    <span>Copied</span>
                                </span>
                            </button>
                        </div>
                        <p class="text-[11px] text-muted mt-1">Callback URL to configure in Meta App Webhooks subscription.</p>
                    </div>
                </div>
            </div>
        @endif

        <!-- FORM: GIT REPOSITORY -->
        @if($activeTab === 'git')
            <div class="space-y-4 text-xs">
                <!-- Row 1: Repository Name & Remote URL -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="font-bold text-ink">Repository Display Name <span class="text-rose-500">*</span></label>
                        <input
                            type="text"
                            wire:model="gitAccountName"
                            value="{{ $gitAccountName }}"
                            placeholder="Git Repository Integration"
                            class="w-full h-9 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:outline-none focus:ring-1 focus:ring-ink transition mt-1"
                        />
                        @error('gitAccountName') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="font-bold text-ink">Remote Repository URL <span class="text-rose-500">*</span></label>
                        <input
                            type="text"
                            wire:model="gitRemoteUrl"
                            value="{{ $gitRemoteUrl }}"
                            placeholder="https://github.com/organization/lead_panther_crm.git"
                            class="w-full h-9 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs font-mono focus:outline-none focus:ring-1 focus:ring-ink transition mt-1"
                        />
                        @error('gitRemoteUrl') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                        <p class="text-[11px] text-muted mt-1">HTTPS clone URL of your GitHub, GitLab, or Bitbucket repository.</p>
                    </div>
                </div>

                <!-- Row 2: Username & Personal Access Token -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="font-bold text-ink">Git Username / Deployer</label>
                        <input
                            type="text"
                            wire:model="gitUsername"
                            value="{{ $gitUsername }}"
                            placeholder="e.g. leadpanther-deployer"
                            class="w-full h-9 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs font-mono focus:outline-none focus:ring-1 focus:ring-ink transition mt-1"
                        />
                        <p class="text-[11px] text-muted mt-1">Your GitHub or GitLab account username.</p>
                    </div>

                    <div>
                        <label class="font-bold text-ink">Personal Access Token (PAT)</label>
                        <div class="relative mt-1">
                            <input
                                :type="showGitToken ? 'text' : 'password'"
                                wire:model="gitAccessToken"
                                value="{{ $gitAccessToken }}"
                                placeholder="ghp_••••••••••••••••••••••••••••••••"
                                class="w-full h-9 pl-3.5 pr-10 rounded-lg border border-border bg-canvas text-ink text-xs font-mono focus:outline-none focus:ring-1 focus:ring-ink transition"
                            />
                            <button
                                type="button"
                                x-on:click="showGitToken = !showGitToken"
                                tabindex="-1"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted hover:text-ink cursor-pointer"
                            >
                                <svg x-show="!showGitToken" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                <svg x-show="showGitToken" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:none;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"></path></svg>
                            </button>
                        </div>
                        <p class="text-[11px] text-muted mt-1">Requires <code class="font-bold text-ink">repo</code> permissions (Encrypted at rest with AES-256).</p>
                    </div>
                </div>

                <!-- Row 3: Default Branch & Committer Name -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="font-bold text-ink">Default Deployment Branch <span class="text-rose-500">*</span></label>
                        <input
                            type="text"
                            wire:model="gitDefaultBranch"
                            value="{{ $gitDefaultBranch }}"
                            placeholder="main"
                            class="w-full h-9 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs font-mono focus:outline-none focus:ring-1 focus:ring-ink transition mt-1"
                        />
                        @error('gitDefaultBranch') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                        <p class="text-[11px] text-muted mt-1">Target branch for production deployments and updates.</p>
                    </div>

                    <div>
                        <label class="font-bold text-ink">Committer Name (Optional)</label>
                        <input
                            type="text"
                            wire:model="gitCommitterName"
                            value="{{ $gitCommitterName }}"
                            placeholder="e.g. Lead Panther Deployer"
                            class="w-full h-9 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:outline-none focus:ring-1 focus:ring-ink transition mt-1"
                        />
                        <p class="text-[11px] text-muted mt-1">Author name recorded in automated git commits.</p>
                    </div>
                </div>

                <!-- Row 4: Committer Email & Working Directory -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="font-bold text-ink">Committer Email (Optional)</label>
                        <input
                            type="email"
                            wire:model="gitCommitterEmail"
                            value="{{ $gitCommitterEmail }}"
                            placeholder="e.g. deploy@leadpanther.com"
                            class="w-full h-9 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:outline-none focus:ring-1 focus:ring-ink transition mt-1"
                        />
                        <p class="text-[11px] text-muted mt-1">Author email recorded in automated git commits.</p>
                    </div>

                    <div>
                        <label class="font-bold text-ink">Active Server Working Directory</label>
                        <input
                            type="text"
                            readonly
                            value="{{ base_path() }}"
                            class="w-full h-9 px-3.5 rounded-lg border border-border bg-canvas/60 text-muted font-mono text-[11px] select-all cursor-text focus:outline-none mt-1"
                        />
                        <p class="text-[11px] text-muted mt-1">Local path of current CRM application repository.</p>
                    </div>
                </div>
            </div>
        @endif

        <!-- Card Footer Action Bar -->
        <div class="flex items-center justify-end space-x-3 pt-4 border-t border-border">
            <button
                type="button"
                wire:click="testCurrentConnection"
                wire:loading.attr="disabled"
                class="px-4 py-2 border border-border bg-white text-ink text-xs font-semibold rounded-xl hover:bg-canvas transition flex items-center gap-2 cursor-pointer shadow-2xs disabled:opacity-60"
            >
                <svg wire:loading.remove wire:target="testCurrentConnection" class="w-3.5 h-3.5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"></path>
                </svg>
                <svg wire:loading wire:target="testCurrentConnection" class="animate-spin w-3.5 h-3.5 text-ink" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>Test Connection</span>
            </button>

            <button
                type="button"
                wire:click="saveAccount"
                wire:loading.attr="disabled"
                class="px-5 py-2 bg-ink text-white text-xs font-bold rounded-xl hover:bg-neutral-800 transition flex items-center gap-2 cursor-pointer shadow-xs disabled:opacity-60"
            >
                <svg wire:loading.remove wire:target="saveAccount" class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                <svg wire:loading wire:target="saveAccount" class="animate-spin w-3.5 h-3.5 text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>Save Encrypted Credentials</span>
            </button>
        </div>

    </div>
</div>
