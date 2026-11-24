<!-- Lead Panther PWA Installation Guidance Modal (iOS Safari & Browser Guidance) -->
<div 
    x-data="{
        showIos: false,
        showHelp: false,
        close() {
            this.showIos = false;
            this.showHelp = false;
        }
    }"
    x-on:open-pwa-ios-modal.window="showIos = true"
    x-on:open-pwa-help-modal.window="showHelp = true"
    x-on:keydown.escape.window="close()"
    x-cloak
>
    <!-- iOS Safari Instructions Modal -->
    <div 
        x-show="showIos"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-[9999] flex items-end sm:items-center justify-center p-0 sm:p-4 bg-black/60 backdrop-blur-xs"
        style="display: none;"
    >
        <!-- Modal Backdrop -->
        <div class="fixed inset-0" x-on:click="close()"></div>

        <!-- Sheet / Dialog Content -->
        <div 
            x-show="showIos"
            x-transition:enter="transition ease-out duration-300 transform"
            x-transition:enter-start="translate-y-full sm:translate-y-4 sm:scale-95"
            x-transition:enter-end="translate-y-0 sm:scale-100"
            x-transition:leave="transition ease-in duration-200 transform"
            x-transition:leave-start="translate-y-0 sm:scale-100"
            x-transition:leave-end="translate-y-full sm:translate-y-4 sm:scale-95"
            class="relative w-full max-w-md bg-surface border border-border rounded-t-3xl sm:rounded-2xl p-6 shadow-2xl z-10 space-y-5 text-ink"
        >
            <!-- Handle bar for mobile bottom sheet swipe feel -->
            <div class="w-12 h-1.5 bg-border rounded-full mx-auto -mt-2 sm:hidden"></div>

            <!-- Header with App Logo -->
            <div class="flex items-center space-x-3.5">
                <img src="{{ asset('icons/icon-192x192.png') }}" alt="Lead Panther Icon" class="w-13 h-13 rounded-2xl shadow-md border border-border/80 shrink-0" />
                <div class="min-w-0">
                    <h3 class="text-base font-bold text-ink tracking-tight">Install Lead Panther</h3>
                    <p class="text-xs text-muted">Add to your iPhone Home Screen for a fast, full-screen app experience.</p>
                </div>
                <button type="button" x-on:click="close()" class="p-1.5 rounded-lg text-muted hover:text-ink hover:bg-canvas transition ml-auto shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Instructions Steps -->
            <div class="space-y-3 bg-canvas/60 p-4 rounded-xl border border-border/70 text-xs">
                <!-- Step 1 -->
                <div class="flex items-start space-x-3">
                    <div class="w-6 h-6 rounded-full bg-primary text-white flex items-center justify-center font-bold text-[11px] shrink-0 mt-0.5">
                        1
                    </div>
                    <div class="flex-1 text-ink leading-relaxed">
                        In <span class="font-semibold">Safari</span>, tap the <span class="font-bold inline-flex items-center gap-1 bg-surface px-1.5 py-0.5 rounded border border-border text-primary"><svg class="w-3.5 h-3.5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg> Share</span> button in the bottom menu bar.
                    </div>
                </div>

                <!-- Step 2 -->
                <div class="flex items-start space-x-3">
                    <div class="w-6 h-6 rounded-full bg-primary text-white flex items-center justify-center font-bold text-[11px] shrink-0 mt-0.5">
                        2
                    </div>
                    <div class="flex-1 text-ink leading-relaxed">
                        Scroll down the menu options and tap <span class="font-bold inline-flex items-center gap-1 bg-surface px-1.5 py-0.5 rounded border border-border"><svg class="w-3.5 h-3.5 inline text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg> Add to Home Screen</span>.
                    </div>
                </div>

                <!-- Step 3 -->
                <div class="flex items-start space-x-3">
                    <div class="w-6 h-6 rounded-full bg-primary text-white flex items-center justify-center font-bold text-[11px] shrink-0 mt-0.5">
                        3
                    </div>
                    <div class="flex-1 text-ink leading-relaxed">
                        Tap <span class="font-bold text-primary">Add</span> in the top-right corner. Lead Panther will appear directly on your home screen!
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end pt-1">
                <button 
                    type="button" 
                    x-on:click="close()" 
                    class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-ink text-white text-xs font-bold hover:bg-neutral-800 transition shadow-sm cursor-pointer"
                >
                    Got it
                </button>
            </div>
        </div>
    </div>

    <!-- Generic Browser Help Modal (When automated prompt was dismissed or browser requires menu install) -->
    <div 
        x-show="showHelp"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-[9999] flex items-end sm:items-center justify-center p-0 sm:p-4 bg-black/60 backdrop-blur-xs"
        style="display: none;"
    >
        <div class="fixed inset-0" x-on:click="close()"></div>

        <div 
            x-show="showHelp"
            x-transition:enter="transition ease-out duration-300 transform"
            x-transition:enter-start="translate-y-full sm:translate-y-4 sm:scale-95"
            x-transition:enter-end="translate-y-0 sm:scale-100"
            x-transition:leave="transition ease-in duration-200 transform"
            x-transition:leave-start="translate-y-0 sm:scale-100"
            x-transition:leave-end="translate-y-full sm:translate-y-4 sm:scale-95"
            class="relative w-full max-w-md bg-surface border border-border rounded-t-3xl sm:rounded-2xl p-6 shadow-2xl z-10 space-y-5 text-ink"
        >
            <div class="w-12 h-1.5 bg-border rounded-full mx-auto -mt-2 sm:hidden"></div>

            <div class="flex items-center space-x-3.5">
                <img src="{{ asset('icons/icon-192x192.png') }}" alt="Lead Panther Icon" class="w-13 h-13 rounded-2xl shadow-md border border-border/80 shrink-0" />
                <div class="min-w-0">
                    <h3 class="text-base font-bold text-ink tracking-tight">Install Lead Panther CRM</h3>
                    <p class="text-xs text-muted">Add to your Home Screen or Desktop for instant standalone access.</p>
                </div>
                <button type="button" x-on:click="close()" class="p-1.5 rounded-lg text-muted hover:text-ink hover:bg-canvas transition ml-auto shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="space-y-3 bg-canvas/60 p-4 rounded-xl border border-border/70 text-xs">
                <div class="flex items-start space-x-3">
                    <div class="w-6 h-6 rounded-full bg-primary text-white flex items-center justify-center font-bold text-[11px] shrink-0 mt-0.5">1</div>
                    <div class="flex-1 text-ink leading-relaxed">
                        Open your browser's menu (<span class="font-bold">⋮</span> on Chrome / Android or <span class="font-bold">⋯</span> on Edge).
                    </div>
                </div>
                <div class="flex items-start space-x-3">
                    <div class="w-6 h-6 rounded-full bg-primary text-white flex items-center justify-center font-bold text-[11px] shrink-0 mt-0.5">2</div>
                    <div class="flex-1 text-ink leading-relaxed">
                        Select <span class="font-semibold bg-surface px-1.5 py-0.5 rounded border border-border">Install Lead Panther</span> or <span class="font-semibold bg-surface px-1.5 py-0.5 rounded border border-border">Add to Home screen</span>.
                    </div>
                </div>
                <div class="flex items-start space-x-3">
                    <div class="w-6 h-6 rounded-full bg-primary text-white flex items-center justify-center font-bold text-[11px] shrink-0 mt-0.5">3</div>
                    <div class="flex-1 text-ink leading-relaxed">
                        Confirm <span class="font-bold text-primary">Install</span> to launch in full-screen standalone mode!
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end pt-1">
                <button 
                    type="button" 
                    x-on:click="close()" 
                    class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-ink text-white text-xs font-bold hover:bg-neutral-800 transition shadow-sm cursor-pointer"
                >
                    Got it
                </button>
            </div>
        </div>
    </div>
</div>
