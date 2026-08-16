@persist('toast-container')
<div 
    id="toast-container"
    wire:persist="toast-container"
    x-data="{
        toasts: [],
        add(toast) {
            let id = Date.now() + Math.random();
            let type = toast.type || 'success';
            let title = toast.title || (type === 'success' ? 'Success' : (type === 'error' ? 'Error' : (type === 'warning' ? 'Warning' : 'Notice')));
            let message = typeof toast === 'string' ? toast : (toast.message || '');

            let item = { id, type, title, message };
            this.toasts.unshift(item);

            setTimeout(() => {
                this.remove(id);
            }, 5000);
        },
        remove(id) {
            this.toasts = this.toasts.filter(t => t.id !== id);
        }
    }"
    x-on:toast.window="add($event.detail)"
    x-on:livewire:navigating.window="toasts = []"
    x-init="
        @if(session()->has('success'))
            add({ type: 'success', message: @json(session('success')) });
        @endif
        @if(session()->has('error'))
            add({ type: 'error', message: @json(session('error')) });
        @endif
        @if(session()->has('warning'))
            add({ type: 'warning', message: @json(session('warning')) });
        @endif
        @if(session()->has('info'))
            add({ type: 'info', message: @json(session('info')) });
        @endif
    "
    class="fixed top-20 right-4 sm:right-6 z-40 flex flex-col gap-3 pointer-events-none"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div 
            x-transition:enter="transition ease-out duration-300 transform"
            x-transition:enter-start="opacity-0 translate-y-[-10px] scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-200 transform"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 translate-y-[-10px] scale-95"
            class="pointer-events-auto bg-surface border border-border rounded-card shadow-lg px-4 py-3 flex items-start gap-3 min-w-[320px] max-w-[420px] relative overflow-hidden"
        >
            <!-- Type Icon Badge -->
            <div class="flex-shrink-0 mt-0.5">
                <template x-if="toast.type === 'success'">
                    <div class="h-8 w-8 rounded-full bg-emerald-50 text-success flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                </template>
                <template x-if="toast.type === 'error'">
                    <div class="h-8 w-8 rounded-full bg-red-50 text-danger flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                </template>
                <template x-if="toast.type === 'warning'">
                    <div class="h-8 w-8 rounded-full bg-amber-50 text-warning flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </div>
                </template>
                <template x-if="toast.type === 'info'">
                    <div class="h-8 w-8 rounded-full bg-blue-50 text-info flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                </template>
            </div>

            <!-- Toast Content -->
            <div class="flex-1 min-w-0 pr-2">
                <template x-if="toast.title">
                    <div class="text-xs font-bold text-ink tracking-tight mb-0.5" x-text="toast.title"></div>
                </template>
                <div class="text-xs font-medium text-ink leading-relaxed" x-text="toast.message"></div>
            </div>

            <!-- Manual Close Button -->
            <button 
                x-on:click="remove(toast.id)" 
                type="button" 
                class="flex-shrink-0 text-muted hover:text-ink transition p-1 -mr-1 rounded-lg focus:outline-none"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>

            <!-- 5s Draining Progress Bar -->
            <div class="absolute bottom-0 left-0 right-0 h-0.5 bg-border/40 overflow-hidden">
                <div 
                    class="h-full transition-all duration-300"
                    :class="{
                        'bg-success': toast.type === 'success',
                        'bg-danger': toast.type === 'error',
                        'bg-warning': toast.type === 'warning',
                        'bg-info': toast.type === 'info'
                    }"
                    style="animation: toast-drain 5s linear forwards;"
                ></div>
            </div>
        </div>
    </template>
</div>
@endpersist
