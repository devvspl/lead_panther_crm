@props(['align' => 'right', 'width' => '48', 'contentClasses' => 'py-1 bg-surface'])

@php
$popupWidth = is_numeric($width) ? (int)$width * 4 : 224;
$alignmentClasses = match ($align) {
    'left' => 'ltr:origin-top-left rtl:origin-top-right start-0',
    'top' => 'origin-top',
    default => 'ltr:origin-top-right rtl:origin-top-left end-0',
};

$width = match ($width) {
    '48' => 'w-48',
    default => $width,
};
@endphp

<div class="relative" x-data="{ open: false, panelStyle: {}, place() { this.panelStyle = window.LeadPantherUI.position(this.$refs.trigger,this.$refs.panel,{{ $popupWidth }},'{{ $align }}'); } }" @close.stop="open = false" @keydown.escape.stop="open = false; $refs.trigger.querySelector('button,a')?.focus()" @resize.window="if(open) place()" @scroll.window.capture="if(open && !$refs.panel.contains($event.target)) place()" x-on:livewire:navigating.window="open=false">
    <div x-ref="trigger" @click="open = !open; if(open) $nextTick(() => place())" :aria-expanded="open">
        {{ $trigger }}
    </div>

    <template x-teleport="body">
    <div x-ref="panel" :style="panelStyle" @click.outside="if(!$refs.trigger.contains($event.target)) open=false" @keydown.escape.stop="open=false; $refs.trigger.querySelector('button,a')?.focus()" x-show="open"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="fixed z-[100] overflow-y-auto overscroll-contain {{ $width }} rounded-md shadow-lg {{ $alignmentClasses }}"
            style="display: none;"
            @click="open = false">
        <div class="rounded-md ring-1 ring-black ring-opacity-5 {{ $contentClasses }}">
            {{ $content }}
        </div>
    </div>
    </template>
</div>
