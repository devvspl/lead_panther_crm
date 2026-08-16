@props([
    'title' => null,
])

<div {{ $attributes->merge(['class' => 'bg-surface rounded-card border border-border p-6 shadow-sm']) }}>
    @if ($title)
        <h3 class="text-lg font-semibold text-ink mb-4">{{ $title }}</h3>
    @endif
    {{ $slot }}
</div>
