@props([
    'title' => null,
    'label' => null,
    'value' => null,
    'delta' => null,
    'isPositive' => null,
    'icon' => null,
])

@php
    $cardTitle = $title ?? $label ?? $this->title ?? $this->label ?? 'Metric';
    $cardValue = $value ?? $this->value ?? '0';
    $cardDelta = $delta ?? $this->delta ?? '0%';
    $cardPositive = $isPositive ?? $this->isPositive ?? true;
    $cardIcon = $icon ?? $this->icon ?? 'chart';
@endphp

<div class="bg-surface rounded-card border border-border p-6 shadow-sm flex items-start justify-between transition hover:shadow-md">
    <div>
        <span class="text-xs font-semibold text-muted uppercase tracking-wider">{{ $cardTitle }}</span>
        <div class="text-3xl font-bold text-ink mt-2 tracking-tight">{{ $cardValue }}</div>
        <div class="mt-3 flex items-center space-x-1.5 text-xs font-semibold {{ $cardPositive ? 'text-success' : 'text-danger' }}">
            <span>{{ $cardPositive ? '▲' : '▼' }}</span>
            <span>{{ $cardDelta }}</span>
            <span class="text-muted font-normal">vs last month</span>
        </div>
    </div>
    <div class="h-8 w-10 rounded-full bg-canvas border border-border flex items-center justify-center text-ink flex-shrink-0">
        @if($cardIcon === 'users')
            <svg class="w-5 h-5 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
        @elseif($cardIcon === 'wallet')
            <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
        @elseif($cardIcon === 'alert-triangle')
            <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        @elseif($cardIcon === 'check-circle')
            <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        @else
            <svg class="w-5 h-5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
        @endif
    </div>
</div>
