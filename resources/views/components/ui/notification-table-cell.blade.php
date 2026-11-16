@switch($column)
    @case('summary')
        <div class="min-w-60 max-w-xl space-y-1">
            <span class="text-[10px] font-semibold uppercase tracking-wide text-muted">{{ ucwords(str_replace('_', ' ', $notification->data['type'] ?? $notification->type)) }}</span>
            <p class="font-semibold text-ink">{{ $notification->data['title'] ?? '' }}</p>
            <p class="text-xs text-muted leading-relaxed break-words">{{ $notification->data['message'] ?? '' }}</p>
        </div>
        @break
    @case('recipient_info')
        <p class="font-semibold text-ink">{{ $notification->recipient?->name ?? 'Recipient unavailable' }}</p>
        <p class="text-xs text-muted mt-1">{{ $notification->recipient?->getRoleNames()->implode(', ') }}</p>
        @break
    @case('organization_info')
        <span class="text-ink">{{ $notification->organization?->name ?? 'System / Unavailable' }}</span>
        @break
    @case('related_record')
        <button type="button" wire:click="markAsReadAndNavigate('{{ $notification->id }}')" class="text-primary font-semibold hover:underline">{{ ucfirst($notification->data['entity_type'] ?? 'Notification') }} {{ $notification->data['entity_id'] ?? '' }}</button>
        @break
    @case('timestamp')
        <span class="whitespace-nowrap text-xs text-muted font-mono">{{ $notification->created_at->format('M d, Y H:i:s') }}</span>
        @break
    @case('read_status')
        <span class="inline-flex rounded-full px-2 py-1 text-[10px] font-semibold {{ $notification->read_at ? 'bg-canvas text-muted border border-border' : 'bg-ink text-white' }}">{{ $notification->read_at ? 'Read' : 'Unread' }}</span>
        @break
    @case('notice_actions')
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" wire:click="inspect('{{ $notification->id }}')" class="rounded-lg border border-border px-2.5 py-1.5 text-xs font-semibold hover:bg-canvas">Inspect</button>
            @unless($notification->read_at)<button type="button" wire:click="markAsRead('{{ $notification->id }}')" class="text-xs font-semibold text-primary hover:underline">Mark read</button>@endunless
        </div>
        @break
@endswitch
