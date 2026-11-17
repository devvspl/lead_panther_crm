@props(['disabled' => false, 'variant' => 'default'])

@php
    $inputClasses = $variant === 'compact'
        ? 'w-full h-8 px-2.5 py-1 border border-border rounded-lg bg-canvas text-ink text-xs placeholder:text-muted focus:outline-none focus:ring-2 focus:ring-ink transition disabled:opacity-50'
        : 'border-border focus:outline-none focus:border-transparent focus:ring-2 focus:ring-ink rounded-lg bg-surface text-ink text-sm shadow-sm transition';
@endphp

<input @disabled($disabled) {{ $attributes->merge(['class' => $inputClasses]) }}>
