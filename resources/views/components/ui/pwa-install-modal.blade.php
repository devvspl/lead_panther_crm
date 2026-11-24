<!-- Lead Panther PWA Installation Guidance Modal using Project Native x-modal -->
<x-modal name="pwa-install-modal" maxWidth="md">
    <div 
        x-data="{
            platform: (function() {
                if (/iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)) return 'ios';
                if (/Android/i.test(navigator.userAgent)) return 'android';
                return 'desktop';
            })(),
            activeTab: 'android',
            hasPrompt: false,
            installed: false,
            init() {
                this.activeTab = this.platform;
                this.installed = Boolean(window.isLeadPantherPWAInstalled);
                this.hasPrompt = Boolean(window.__pwaInstallPrompt);
                window.addEventListener('pwa-prompt-available', () => { this.hasPrompt = true; });
                window.addEventListener('pwa-installed', () => { this.installed = true; });
                window.addEventListener('pwa-select-tab', (e) => {
                    if (e.detail && ['android', 'ios', 'desktop'].includes(e.detail)) {
                        this.activeTab = e.detail;
                    }
                });
            },
            close() {
                $dispatch('close-modal', 'pwa-install-modal');
            },
            installApp() {
                if (window.LeadPantherPWA && typeof window.LeadPantherPWA.promptAndroid === 'function') {
                    window.LeadPantherPWA.promptAndroid();
                } else if (window.__pwaInstallPrompt) {
                    window.__pwaInstallPrompt.prompt();
                }
            }
        }"
        class="p-5 sm:p-6 space-y-4 text-ink select-none"
    >
        <!-- Modal Header -->
        <div class="flex items-center justify-between pb-3.5 border-b border-border">
            <div class="flex items-center gap-3 min-w-0">
                <img src="{{ asset('icons/icon-192x192.png') }}" alt="Lead Panther" class="w-10 h-10 rounded-xl border border-border shadow-xs object-cover shrink-0" />
                <div class="min-w-0">
                    <h3 class="text-base font-bold text-ink truncate">Install Lead Panther</h3>
                    <p class="text-xs text-muted truncate">Fast, full-screen CRM app on your device</p>
                </div>
            </div>
            <button 
                type="button" 
                x-on:click="close()" 
                class="text-muted hover:text-ink cursor-pointer p-1.5 rounded-lg hover:bg-canvas transition"
                aria-label="Close modal"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- App Features / Quick Highlights -->
        <div class="grid grid-cols-3 gap-2 text-center text-xs">
            <div class="bg-canvas border border-border/80 rounded-lg p-2.5">
                <div class="font-bold text-ink flex items-center justify-center gap-1">
                    <svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                    <span>Instant</span>
                </div>
                <p class="text-[10px] text-muted mt-0.5">Quick access</p>
            </div>
            <div class="bg-canvas border border-border/80 rounded-lg p-2.5">
                <div class="font-bold text-ink flex items-center justify-center gap-1">
                    <svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                    <span>Standalone</span>
                </div>
                <p class="text-[10px] text-muted mt-0.5">Full screen</p>
            </div>
            <div class="bg-canvas border border-border/80 rounded-lg p-2.5">
                <div class="font-bold text-ink flex items-center justify-center gap-1">
                    <svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>
                    <span>Alerts</span>
                </div>
                <p class="text-[10px] text-muted mt-0.5">Push updates</p>
            </div>
        </div>

        <!-- Platform Segmented Tabs -->
        <div class="flex items-center rounded-lg bg-canvas p-1 border border-border/70 text-xs font-medium">
            <button 
                type="button" 
                x-on:click="activeTab = 'android'" 
                :class="activeTab === 'android' ? 'bg-surface text-ink font-semibold shadow-2xs border border-border/60' : 'text-muted hover:text-ink'"
                class="flex-1 py-1.5 px-2 rounded-md transition text-center cursor-pointer"
            >
                Android
            </button>
            <button 
                type="button" 
                x-on:click="activeTab = 'ios'" 
                :class="activeTab === 'ios' ? 'bg-surface text-ink font-semibold shadow-2xs border border-border/60' : 'text-muted hover:text-ink'"
                class="flex-1 py-1.5 px-2 rounded-md transition text-center cursor-pointer"
            >
                iPhone / iPad
            </button>
            <button 
                type="button" 
                x-on:click="activeTab = 'desktop'" 
                :class="activeTab === 'desktop' ? 'bg-surface text-ink font-semibold shadow-2xs border border-border/60' : 'text-muted hover:text-ink'"
                class="flex-1 py-1.5 px-2 rounded-md transition text-center cursor-pointer"
            >
                Desktop
            </button>
        </div>

        <!-- Android Content -->
        <div x-show="activeTab === 'android'" class="space-y-3">
            <template x-if="hasPrompt && !installed">
                <div class="bg-primary/5 border border-primary/20 rounded-xl p-4 text-center space-y-3">
                    <p class="text-xs text-ink leading-relaxed">
                        Tap the button below to install the Lead Panther App directly on your Android device.
                    </p>
                    <button 
                        type="button" 
                        x-on:click="installApp()" 
                        class="w-full py-2.5 px-4 bg-ink text-white font-semibold text-xs rounded-xl hover:bg-neutral-800 transition shadow-sm flex items-center justify-center gap-2 cursor-pointer"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                        <span>Install Lead Panther App</span>
                    </button>
                </div>
            </template>
            <template x-if="!hasPrompt && !installed">
                <div class="space-y-2.5 bg-canvas/60 p-3.5 rounded-xl border border-border/70 text-xs">
                    <div class="flex items-start gap-2.5">
                        <div class="w-5 h-5 rounded-full bg-ink text-white flex items-center justify-center font-bold text-[10px] shrink-0 mt-0.5">1</div>
                        <div class="flex-1 text-ink">In Chrome, tap the <span class="font-bold">⋮</span> (three dots menu) in the top-right.</div>
                    </div>
                    <div class="flex items-start gap-2.5">
                        <div class="w-5 h-5 rounded-full bg-ink text-white flex items-center justify-center font-bold text-[10px] shrink-0 mt-0.5">2</div>
                        <div class="flex-1 text-ink">Tap <span class="font-semibold bg-surface px-1.5 py-0.5 rounded border border-border">Install App</span> or <span class="font-semibold bg-surface px-1.5 py-0.5 rounded border border-border">Add to Home screen</span>.</div>
                    </div>
                    <div class="flex items-start gap-2.5">
                        <div class="w-5 h-5 rounded-full bg-ink text-white flex items-center justify-center font-bold text-[10px] shrink-0 mt-0.5">3</div>
                        <div class="flex-1 text-ink">Confirm <span class="font-bold text-ink">Install</span> to launch CRM in full-screen mode!</div>
                    </div>
                </div>
            </template>
            <template x-if="installed">
                <div class="bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-800 rounded-xl p-4 text-center space-y-1">
                    <div class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto mb-1">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    </div>
                    <p class="text-xs font-bold text-emerald-800 dark:text-emerald-300">Lead Panther is already installed</p>
                    <p class="text-[11px] text-emerald-700 dark:text-emerald-400">Launch it anytime directly from your phone's home screen icon.</p>
                </div>
            </template>
        </div>

        <!-- iOS Safari Content -->
        <div x-show="activeTab === 'ios'" class="space-y-3">
            <div class="space-y-2.5 bg-canvas/60 p-3.5 rounded-xl border border-border/70 text-xs">
                <div class="flex items-start gap-2.5">
                    <div class="w-5 h-5 rounded-full bg-ink text-white flex items-center justify-center font-bold text-[10px] shrink-0 mt-0.5">1</div>
                    <div class="flex-1 text-ink leading-relaxed">
                        In <span class="font-semibold">Safari</span>, tap the <span class="font-bold inline-flex items-center gap-1 bg-surface px-1.5 py-0.5 rounded border border-border text-primary"><svg class="w-3.5 h-3.5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg> Share</span> button at the bottom of Safari.
                    </div>
                </div>
                <div class="flex items-start gap-2.5">
                    <div class="w-5 h-5 rounded-full bg-ink text-white flex items-center justify-center font-bold text-[10px] shrink-0 mt-0.5">2</div>
                    <div class="flex-1 text-ink leading-relaxed">
                        Scroll down and tap <span class="font-bold inline-flex items-center gap-1 bg-surface px-1.5 py-0.5 rounded border border-border"><svg class="w-3.5 h-3.5 inline text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg> Add to Home Screen</span>.
                    </div>
                </div>
                <div class="flex items-start gap-2.5">
                    <div class="w-5 h-5 rounded-full bg-ink text-white flex items-center justify-center font-bold text-[10px] shrink-0 mt-0.5">3</div>
                    <div class="flex-1 text-ink leading-relaxed">
                        Tap <span class="font-bold text-ink">Add</span> in the top-right corner. Lead Panther will appear directly on your home screen!
                    </div>
                </div>
            </div>
            <p class="text-[11px] text-muted text-center italic">
                Note: Must be opened in Apple Safari on iPhone to add to home screen.
            </p>
        </div>

        <!-- Desktop Content -->
        <div x-show="activeTab === 'desktop'" class="space-y-3">
            <template x-if="hasPrompt && !installed">
                <div class="bg-primary/5 border border-primary/20 rounded-xl p-4 text-center space-y-3">
                    <p class="text-xs text-ink leading-relaxed">
                        Install Lead Panther on your desktop for a dedicated window, faster performance and offline access.
                    </p>
                    <button 
                        type="button" 
                        x-on:click="installApp()" 
                        class="w-full py-2.5 px-4 bg-ink text-white font-semibold text-xs rounded-xl hover:bg-neutral-800 transition shadow-sm flex items-center justify-center gap-2 cursor-pointer"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                        <span>Install Lead Panther Desktop App</span>
                    </button>
                </div>
            </template>
            <template x-if="!hasPrompt && !installed">
                <div class="space-y-2.5 bg-canvas/60 p-3.5 rounded-xl border border-border/70 text-xs">
                    <div class="flex items-start gap-2.5">
                        <div class="w-5 h-5 rounded-full bg-ink text-white flex items-center justify-center font-bold text-[10px] shrink-0 mt-0.5">1</div>
                        <div class="flex-1 text-ink">In Chrome / Edge, look at the right end of the address bar for the <span class="font-bold">Install</span> icon (⊕ or computer screen icon).</div>
                    </div>
                    <div class="flex items-start gap-2.5">
                        <div class="w-5 h-5 rounded-full bg-ink text-white flex items-center justify-center font-bold text-[10px] shrink-0 mt-0.5">2</div>
                        <div class="flex-1 text-ink">Or click browser menu <span class="font-bold">⋮</span> → <span class="font-semibold bg-surface px-1.5 py-0.5 rounded border border-border">Install Lead Panther</span>.</div>
                    </div>
                    <div class="flex items-start gap-2.5">
                        <div class="w-5 h-5 rounded-full bg-ink text-white flex items-center justify-center font-bold text-[10px] shrink-0 mt-0.5">3</div>
                        <div class="flex-1 text-ink">Click <span class="font-bold text-ink">Install</span> to launch CRM in its own clean window.</div>
                    </div>
                </div>
            </template>
            <template x-if="installed">
                <div class="bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-800 rounded-xl p-4 text-center space-y-1">
                    <div class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto mb-1">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    </div>
                    <p class="text-xs font-bold text-emerald-800 dark:text-emerald-300">Lead Panther is installed</p>
                    <p class="text-[11px] text-emerald-700 dark:text-emerald-400">You are running the app or have it pinned to your desktop.</p>
                </div>
            </template>
        </div>

        <!-- Modal Footer Actions -->
        <div class="flex items-center justify-end gap-2 pt-2 border-t border-border">
            <button 
                type="button" 
                x-on:click="close()" 
                class="px-4 py-2 bg-surface border border-border text-ink rounded-lg font-medium text-xs hover:bg-canvas transition shadow-xs cursor-pointer"
            >
                Close
            </button>
            <template x-if="hasPrompt && !installed">
                <button 
                    type="button" 
                    x-on:click="installApp()" 
                    class="px-4 py-2 bg-ink text-white rounded-lg font-semibold text-xs hover:bg-neutral-800 transition shadow-xs flex items-center gap-1.5 cursor-pointer"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                    <span>Install Now</span>
                </button>
            </template>
        </div>
    </div>
</x-modal>
