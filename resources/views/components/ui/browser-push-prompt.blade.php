@auth
<div wire:ignore data-browser-push data-push-prompt="browser-push-enable-prompt" data-base="{{ url('/notifications/push') }}" data-prompt-key="push-prompt-{{ auth()->id() }}-{{ substr(hash('sha256', session()->getId()), 0, 12) }}">
    <x-modal name="browser-push-enable-prompt" maxWidth="md" focusable>
        <div class="p-6 sm:p-8 space-y-5" role="dialog" aria-modal="true" aria-labelledby="push-prompt-title" aria-describedby="push-prompt-description">
            <div class="h-12 w-12 rounded-xl bg-canvas border border-border flex items-center justify-center text-ink">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 00-4-5.7V5a2 2 0 10-4 0v.3A6 6 0 006 11v3.2a2 2 0 01-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
            </div>
            <div class="space-y-2">
                <h2 id="push-prompt-title" class="text-lg font-bold text-ink">Enable browser notifications?</h2>
                <p id="push-prompt-description" class="text-sm text-muted leading-relaxed">Get updates about your leads, assignments and follow-ups while you work. Enable notifications for this browser so you can stay up to date.</p>
            </div>
            <p data-push-status class="text-xs text-muted" role="status" aria-live="polite">Checking browser notification settings...</p>
            <div class="flex flex-col sm:flex-row gap-3">
                <button type="button" data-push-action="enable" class="rounded-lg bg-ink text-white px-4 py-2.5 text-xs font-semibold disabled:opacity-50" disabled>Enable Notifications</button>
                <button type="button" data-push-action="dismiss" class="rounded-lg border border-border text-ink px-4 py-2.5 text-xs font-semibold">Not Now</button>
            </div>
            <p class="text-[11px] text-muted">Your browser will ask for permission after you click Enable. You can change this later from the notification bell.</p>
        </div>
    </x-modal>
</div>
@endauth
