<div wire:ignore data-browser-push data-base="{{ url('/notifications/push') }}" class="border-t border-border p-3 space-y-2 text-xs">
    <div class="flex items-center justify-between gap-2"><strong>Browser notifications</strong><span data-push-status class="text-muted" role="status" aria-live="polite">Checking...</span></div>
    <div class="flex flex-wrap gap-2">
        <button type="button" data-push-action="enable" class="rounded-lg bg-ink px-3 py-2 text-white disabled:opacity-50" disabled>Enable on this browser</button>
        <button type="button" data-push-action="disable" class="rounded-lg border border-border px-3 py-2 disabled:opacity-50" disabled>Disable</button>
        <button type="button" data-push-action="test" class="rounded-lg border border-border px-3 py-2 disabled:opacity-50" disabled>Test Push</button>
        <button type="button" data-push-action="desktop" class="rounded-lg border border-border px-3 py-2 disabled:opacity-50" disabled>Test Desktop Popup</button>
    </div>
    <p class="text-muted">For Windows popups, allow this site's notifications and enable notification banners for Chrome/Edge in Windows Settings → System → Notifications. Turn off Do not disturb.</p>
</div>
