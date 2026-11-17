@auth
<div wire:ignore data-browser-push data-push-prompt="browser-push-enable-prompt" data-base="{{ url('/notifications/push') }}" data-prompt-key="push-prompt-{{ auth()->id() }}-{{ substr(hash('sha256', session()->getId()), 0, 12) }}">
    <x-ui.modal name="browser-push-enable-prompt" maxWidth="md" :centered="true">
        <div role="dialog" aria-modal="true" aria-labelledby="push-prompt-title" aria-describedby="push-prompt-description">
            <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-border">
                <h2 id="push-prompt-title" class="text-sm font-bold text-ink">Enable browser notifications?</h2>
                <button type="button" @click="$dispatch('close-modal', 'browser-push-enable-prompt')" aria-label="Close notification prompt" class="p-1 text-muted hover:text-ink rounded-lg hover:bg-canvas">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
            <div class="p-5 space-y-4">
                <p id="push-prompt-description" class="text-xs text-muted leading-relaxed">Receive updates about your leads, assignments and follow-ups on this browser.</p>
                <p data-push-status class="rounded-lg border border-border bg-canvas px-3 py-2 text-xs text-muted" role="status" aria-live="polite">Checking browser notification settings...</p>
                <p class="text-[11px] text-muted leading-relaxed">Click Enable to open your browser's permission request. You can change this later from the notification bell.</p>
            </div>
            <div class="flex flex-wrap justify-end items-center gap-2 px-5 py-3 border-t border-border bg-canvas">
                <x-ui.button variant="secondary" data-push-action="dismiss" class="!text-xs">Not Now</x-ui.button>
                <x-ui.button data-push-action="enable" class="!text-xs disabled:opacity-50" disabled>Enable Notifications</x-ui.button>
            </div>
        </div>
    </x-ui.modal>
</div>
@endauth
