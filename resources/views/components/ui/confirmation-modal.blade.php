<!-- Lead Panther Global Confirmation Modal Component -->
<div x-data="leadPantherConfirmModal()" x-init="init()" x-cloak x-show="open"
    x-on:keydown.escape.window="handleCancel()" role="dialog" aria-modal="true" aria-labelledby="confirm-modal-title"
    aria-describedby="confirm-modal-desc"
    class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6 overflow-y-auto">
    <!-- Smooth Backdrop with Blur -->
    <div x-show="open" x-transition:enter="transition-opacity ease-out duration-200"
        x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-in duration-150" x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0" @click="handleCancel()"
        class="fixed inset-0 bg-black/60 backdrop-blur-xs transition-opacity" aria-hidden="true"></div>

    <!-- Centered Card Container -->
    <div x-show="open" x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95 translate-y-2"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100 translate-y-0"
        x-transition:leave-end="opacity-0 scale-95 translate-y-2"
        class="relative w-full max-w-md bg-surface rounded-card border border-border p-5 sm:p-6 shadow-2xl space-y-4 sm:space-y-5 transform transition-all z-10">
        
        <!-- Top-Right Close Button -->
        <button type="button" @click="handleCancel()" :disabled="loading" aria-label="Close confirmation dialog"
            class="absolute top-3.5 right-3.5 w-7 h-7 flex items-center justify-center text-muted hover:text-ink disabled:opacity-50 transition rounded-lg hover:bg-canvas cursor-pointer focus:outline-none focus:ring-2 focus:ring-ink">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>

        <!-- Main Content Area (Icon Badge + Title + Description) -->
        <div class="flex items-start gap-4">
            <!-- Refined Variant Icon Badge -->
            <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0" :class="{
                    'bg-red-500/10 border border-red-500/20 text-red-600 dark:text-red-400': variant === 'danger',
                    'bg-amber-500/10 border border-amber-500/20 text-amber-600 dark:text-amber-400': variant === 'warning',
                    'bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400': variant === 'success',
                    'bg-blue-500/10 border border-blue-500/20 text-blue-600 dark:text-blue-400': variant === 'info',
                    'bg-primary/10 border border-primary/20 text-primary': variant === 'primary' || !['danger','warning','success','info'].includes(variant)
                }">
                <!-- Danger Icon -->
                <template x-if="variant === 'danger'">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                </template>

                <!-- Warning Icon (Lucide-Style Triangle) -->
                <template x-if="variant === 'warning'">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M12 9v4m0 4h.01M10.29 3.86l-8.82 15A2 2 0 003.18 22h17.64a2 2 0 001.71-3.14l-8.82-15a2 2 0 00-3.42 0z" />
                    </svg>
                </template>

                <!-- Success Icon -->
                <template x-if="variant === 'success'">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </template>

                <!-- Info Icon -->
                <template x-if="variant === 'info'">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </template>

                <!-- Primary Icon -->
                <template x-if="variant === 'primary' || !['danger','warning','success','info'].includes(variant)">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </template>
            </div>

            <!-- Title & Description Text -->
            <div class="flex-1 min-w-0 pr-4">
                <h2 id="confirm-modal-title" class="text-sm sm:text-base font-bold text-ink" x-text="title"></h2>
                <p id="confirm-modal-desc" class="text-xs text-muted leading-relaxed mt-1" x-text="message"></p>
            </div>
        </div>

        <!-- Footer Action Buttons -->
        <div class="pt-3.5 border-t border-border flex items-center justify-end gap-2.5">
            <button type="button" @click="handleCancel()" :disabled="loading"
                class="px-3.5 py-1.5 rounded-lg border border-border bg-canvas text-ink text-xs font-semibold hover:bg-surface disabled:opacity-50 transition shadow-2xs cursor-pointer focus:outline-none focus:ring-2 focus:ring-ink"
                x-text="cancelText">
                Cancel
            </button>

            <button type="button" x-ref="confirmBtn" @click="handleConfirm()" :disabled="loading"
                class="px-4 py-1.5 rounded-lg text-white text-xs font-semibold disabled:opacity-60 transition shadow-sm flex items-center gap-2 cursor-pointer focus:outline-none focus:ring-2"
                :class="{
                    'bg-red-600 hover:bg-red-700 focus:ring-red-500': variant === 'danger',
                    'bg-amber-600 hover:bg-amber-700 focus:ring-amber-500': variant === 'warning',
                    'bg-emerald-600 hover:bg-emerald-700 focus:ring-emerald-500': variant === 'success',
                    'bg-blue-600 hover:bg-blue-700 focus:ring-blue-500': variant === 'info',
                    'bg-primary hover:opacity-90 focus:ring-primary': variant === 'primary' || !['danger','warning','success','info'].includes(variant)
                }">
                <svg x-show="loading" class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                    </path>
                </svg>
                <span x-text="loading ? 'Processing...' : confirmText"></span>
            </button>
        </div>
    </div>
</div>

<script>
    document.addEventListener('alpine:init', function () {
        Alpine.data('leadPantherConfirmModal', function () {
            return {
                open: false,
                loading: false,
                title: 'Are you sure?',
                message: 'Please confirm that you want to perform this action.',
                confirmText: 'Confirm',
                cancelText: 'Cancel',
                variant: 'primary',
                icon: null,
                onConfirm: null,
                onCancel: null,
                livewireTarget: null,
                livewireMethod: null,
                livewireParams: [],
                eventOnConfirm: null,
                eventParams: null,
                previousActiveElement: null,

                init: function () {
                    const self = this;
                    window.addEventListener('confirm-action', function (e) {
                        self.showModal(e.detail || {});
                    });
                    window.addEventListener('open-confirmation-modal', function (e) {
                        self.showModal(e.detail || {});
                    });
                },

                showModal: function (options) {
                    this.previousActiveElement = document.activeElement;
                    this.title = options.title || 'Are you sure?';
                    this.message = options.message || 'Please confirm that you want to perform this action.';
                    this.confirmText = options.confirmText || 'Confirm';
                    this.cancelText = options.cancelText || 'Cancel';
                    this.variant = options.variant || 'primary';
                    this.icon = options.icon || null;
                    this.onConfirm = typeof options.onConfirm === 'function' ? options.onConfirm : null;
                    this.onCancel = typeof options.onCancel === 'function' ? options.onCancel : null;
                    this.livewireTarget = options.livewireTarget || null;
                    this.livewireMethod = options.livewireMethod || null;
                    this.livewireParams = options.livewireParams || [];
                    this.eventOnConfirm = options.eventOnConfirm || null;
                    this.eventParams = options.eventParams || null;
                    this.loading = false;
                    this.open = true;

                    document.body.classList.add('overflow-hidden');

                    const self = this;
                    this.$nextTick(function () {
                        if (self.$refs.confirmBtn) {
                            self.$refs.confirmBtn.focus();
                        }
                    });
                },

                closeModal: function () {
                    if (this.loading) return;
                    this.open = false;
                    this.loading = false;
                    document.body.classList.remove('overflow-hidden');
                    if (this.previousActiveElement && typeof this.previousActiveElement.focus === 'function') {
                        this.previousActiveElement.focus();
                    }
                },

                handleCancel: function () {
                    if (this.loading) return;
                    if (this.onCancel) {
                        try { this.onCancel(); } catch (err) { console.error(err); }
                    }
                    this.closeModal();
                },

                handleConfirm: async function () {
                    if (this.loading) return;
                    this.loading = true;

                    try {
                        // 1. JavaScript Callback
                        if (this.onConfirm) {
                            const result = this.onConfirm();
                            if (result instanceof Promise) {
                                await result;
                            }
                        }

                        // 2. Livewire Target Method
                        if (this.livewireMethod) {
                            let target = window.Livewire;
                            if (this.livewireTarget && window.Livewire && typeof window.Livewire.find === 'function') {
                                const specificComponent = window.Livewire.find(this.livewireTarget);
                                if (specificComponent) {
                                    target = specificComponent;
                                }
                            }
                            if (target && typeof target.call === 'function') {
                                const params = Array.isArray(this.livewireParams) ? this.livewireParams : [this.livewireParams];
                                await target.call(this.livewireMethod, ...params);
                            }
                        }

                        // 3. Custom Event Dispatch
                        if (this.eventOnConfirm) {
                            window.dispatchEvent(new CustomEvent(this.eventOnConfirm, { detail: this.eventParams }));
                        }

                        this.loading = false;
                        this.open = false;
                        document.body.classList.remove('overflow-hidden');
                        if (this.previousActiveElement && typeof this.previousActiveElement.focus === 'function') {
                            this.previousActiveElement.focus();
                        }
                    } catch (error) {
                        this.loading = false;
                        console.error('Confirmation action error:', error);
                        window.dispatchEvent(new CustomEvent('toast-alert', {
                            detail: { type: 'error', message: (error && error.message) ? error.message : 'An error occurred during action execution.' }
                        }));
                    }
                }
            };
        });
    });

    // Global promise-based helper
    window.$confirm = function (options) {
        if (typeof options === 'string') {
            options = { message: options };
        }
        return new Promise(function (resolve) {
            window.dispatchEvent(new CustomEvent('confirm-action', {
                detail: Object.assign({}, options, {
                    onConfirm: function () { resolve(true); },
                    onCancel: function () { resolve(false); }
                })
            }));
        });
    };
</script>