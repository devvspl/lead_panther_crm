@props(['id', 'name', 'available', 'canConfigure', 'priority' => false])
@if($canConfigure)
    @if($priority)
        <x-text-input variant="compact" type="number" min="1" wire:model="settings.members.{{ (int)$id }}.priority" aria-label="Priority for {{ $name }}" />
    @else
        <x-ui.team-switch wire:model="settings.members.{{ (int)$id }}.available" :label="'Available'" aria-label="Availability for {{ $name }}" />
    @endif
@else
    <span class="inline-flex rounded-full px-2 py-1 text-xs {{ $available ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning' }}">{{ $available ? 'Available' : 'Unavailable' }}</span>
@endif
