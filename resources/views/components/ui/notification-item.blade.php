@props(['notification'])
@php
    $data = $notification->data;
    $unread = $notification->read_at === null;
    $category = $data['category'] ?? 'delivery';
    $when = $notification->created_at->copy()->setTimezone(auth()->user()->organization?->delivery_timezone ?: config('app.timezone'));
@endphp
<div wire:key="notification-{{ $notification->id }}" class="flex items-start gap-3 p-4 border-b border-border {{ $unread ? 'bg-primary/5' : 'bg-surface' }}">
    <span class="shrink-0 text-primary mt-1" aria-hidden="true">
        @if(in_array($category, ['followup', 'sla']))
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        @elseif($category === 'assignment')
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2m11-14a4 4 0 11-8 0 4 4 0 018 0zm5 1v6m-3-3h6"/></svg>
        @else
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M4 4v6h6m10 10v-6h-6M5 10a7 7 0 0112-5l3 3M4 16l3 3a7 7 0 0012-5"/></svg>
        @endif
    </span>
    <a href="{{ \Illuminate\Support\Facades\Route::has('notifications.open') ? route('notifications.open', ['notification' => $notification->id]) : '#' }}" wire:click.prevent="markAsReadAndNavigate('{{ $notification->id }}')" class="flex-1 min-w-0 space-y-1 hover:text-primary">
        <p class="text-xs font-bold text-ink break-words">{{ $data['title'] ?? 'Notification' }} @if($unread)<span class="inline-block rounded-full w-2 h-2 bg-primary ml-1" aria-label="Unread"></span>@endif</p>
        <p class="text-xs text-muted break-words">{{ $data['message'] ?? '' }}</p>
        <time datetime="{{ $notification->created_at->toIso8601String() }}" title="{{ $when->format('M d, Y H:i:s T') }}" class="block text-[10px] text-muted">{{ $when->diffForHumans() }} · {{ $when->format('M d, Y H:i') }}</time>
    </a>
    @if($unread)<button type="button" wire:click="markAsRead('{{ $notification->id }}')" title="Mark as read" aria-label="Mark notification as read" class="shrink-0 text-xs text-primary p-1 hover:bg-canvas rounded">✓</button>@endif
</div>
