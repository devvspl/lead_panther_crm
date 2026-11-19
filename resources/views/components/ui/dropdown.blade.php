@props([
    'align' => 'right',
    'width' => '56',
    'contentClasses' => 'py-1 bg-surface'
])

@php
$popupWidth = is_numeric($width) ? (int)$width * 4 : 224;
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

<div class="relative inline-flex items-center text-left" x-data="{ open: false, panelStyle: {}, place() { this.panelStyle = window.LeadPantherUI.position(this.$refs.trigger,this.$refs.panel,{{ $popupWidth }},'{{ $align }}'); } }" @close.stop="open = false" @keydown.escape.stop="open = false; $refs.trigger.querySelector('button,a')?.focus()" @resize.window="if(open) place()" @scroll.window.capture="if(open && !$refs.panel.contains($event.target)) place()" x-on:livewire:navigating.window="open=false">
    <div x-ref="trigger" @click="open = !open; if(open) $nextTick(() => place())" :aria-expanded="open" class="cursor-pointer">
        {{ $trigger }}
    </div>

    <template x-teleport="body">
    <div x-ref="panel" :style="panelStyle" @click.outside="if(!$refs.trigger.contains($event.target)) open=false" @keydown.escape.stop="open=false; $refs.trigger.querySelector('button,a')?.focus()" x-show="open"
            x-cloak
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
            class="fixed z-[100] overflow-y-auto overscroll-contain {{ $widthClass }} rounded-lg shadow-xl border border-border bg-surface text-ink {{ $alignmentClasses }}"
            style="display: none;"
            @click="open = false">
        <div class="rounded-lg {{ $contentClasses }}">
            {{ $content }}
        </div>
    </div>
    </template>
</div>
