@props([
    'align' => 'right',
    'width' => '56',
    'contentClasses' => 'py-1 bg-surface'
])

@php
$alignmentClasses = match ($align) {
    'left' => 'origin-top-left left-0',
    'top' => 'origin-top',
    'right' => 'origin-top-right right-0',
    default => 'origin-top-right right-0',
};

$widthClass = match ($width) {
    '48' => 'w-48',
    '56' => 'w-56',
    '64' => 'w-64',
    '72' => 'w-72',
    default => 'w-56',
};
@endphp

<div class="relative inline-flex items-center text-left" x-data="{ open: false }" @click.outside="open = false" @close.stop="open = false">
    <div @click="open = ! open" class="cursor-pointer">
        {{ $trigger }}
    </div>

    <div x-show="open"
            x-cloak
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
            class="absolute z-50 top-full mt-2 {{ $widthClass }} rounded-lg shadow-xl border border-border bg-surface text-ink {{ $alignmentClasses }}"
            style="display: none;"
            @click="open = false">
        <div class="rounded-lg {{ $contentClasses }}">
            {{ $content }}
        </div>
    </div>
</div>
