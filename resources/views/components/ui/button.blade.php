@props([
    'variant' => 'primary',
    'type' => 'button',
])

@php
$classes = match($variant) {
    'primary' => 'bg-accent hover:bg-black text-white font-medium py-2 px-4 rounded-lg shadow-sm transition ease-in-out duration-150 text-sm',
    'secondary' => 'border border-border bg-white hover:bg-canvas text-ink font-medium py-2 px-4 rounded-lg shadow-sm transition ease-in-out duration-150 text-sm',
    'danger' => 'bg-danger hover:bg-red-700 text-white font-medium py-2 px-4 rounded-lg shadow-sm transition ease-in-out duration-150 text-sm',
    default => 'bg-accent hover:bg-black text-white font-medium py-2 px-4 rounded-lg shadow-sm transition ease-in-out duration-150 text-sm',
};
@endphp

<button {{ $attributes->merge(['type' => $type, 'class' => $classes]) }}>
    {{ $slot }}
</button>
