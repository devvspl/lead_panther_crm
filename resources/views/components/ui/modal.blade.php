@props([
    'name',
    'show' => false,
    'maxWidth' => '2xl',
    'centered' => false,
])

@php
$maxWidthClass = match ($maxWidth) {
    'sm' => 'sm:max-w-sm',
    'md' => 'sm:max-w-md',
    'lg' => 'sm:max-w-lg',
    'xl' => 'sm:max-w-xl',
    '2xl' => 'sm:max-w-2xl',
    default => 'sm:max-w-2xl',
};
@endphp

<div
    x-data="{ show: @js($show), destroy() { window.LeadPantherUI.lock(this.$el,false); } }"
    x-effect="window.LeadPantherUI.lock($el,show)"
    x-show="show"
    x-on:open-modal.window="$event.detail === '{{ $name }}' ? show = true : null"
    x-on:close-modal.window="$event.detail === '{{ $name }}' ? show = false : null"
    x-on:close.stop="show = false"
    x-on:keydown.escape.window="show = false"
    role="dialog" aria-modal="true" aria-label="{{ str_replace('-', ' ', $name) }}"
    class="fixed inset-0 overflow-y-auto px-4 py-6 sm:px-0 z-50"
    style="display: {{ $show ? 'block' : 'none' }};"
>
    <div x-show="show" class="fixed inset-0 transform transition-all" x-on:click="show = false">
        <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm"></div>
    </div>

    <div class="{{ $centered ? 'relative flex min-h-[calc(100vh-3rem)] items-center justify-center' : '' }}" @if($centered) x-on:click.self="show = false" @endif>
        <div x-show="show" class="relative w-full max-h-[calc(100dvh-3rem)] overflow-y-auto overscroll-contain mb-6 bg-surface rounded-xl shadow-xl transform transition-all sm:w-full {{ $maxWidthClass }} sm:mx-auto">
            {{ $slot }}
        </div>
    </div>
</div>
