@props([
    'name',
    'title' => '',
    'subtitle' => '',
    'maxWidth' => 'lg', // sm (384px), md (448px), lg (512px), xl (576px), 2xl (672px)
])

@php
$wireModel = $attributes->wire('model')->value();
$maxWidthClass = match ($maxWidth) {
    'sm' => 'max-w-sm',
    'md' => 'max-w-md',
    'lg' => 'max-w-lg',
    'xl' => 'max-w-xl',
    '2xl' => 'max-w-2xl',
    default => 'max-w-lg',
};
@endphp

<template x-teleport="body">
    <div
        x-data="{ 
            show: @if($wireModel) @entangle($wireModel) @else false @endif,
            checkEvent(detail) {
                if (!detail) return false;
                if (typeof detail === 'string') return detail === '{{ $name }}';
                if (Array.isArray(detail)) return detail.includes('{{ $name }}') || detail[0] === '{{ $name }}';
                if (typeof detail === 'object') return detail.name === '{{ $name }}' || detail[0] === '{{ $name }}';
                return false;
            },
            open() { this.show = true; },
            close() { 
                this.show = false; 
                @if($wireModel)
                    $wire.set('{{ $wireModel }}', false);
                @endif
                $dispatch('offcanvas-closed', '{{ $name }}');
            }
        }"
        x-init="$watch('show', value => {
            if (value) document.body.classList.add('overflow-hidden');
            else document.body.classList.remove('overflow-hidden');
        })"
        x-show="show"
        x-cloak
        x-on:open-offcanvas.window="if (checkEvent($event.detail)) open()"
        x-on:close-offcanvas.window="if (checkEvent($event.detail)) close()"
        x-on:keydown.escape.window="close()"
        class="fixed inset-0 z-50 overflow-hidden"
    >
        <!-- Backdrop -->
        <div 
            x-show="show"
            x-transition:enter="transition-opacity ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            x-on:click="close()"
            class="fixed inset-0 bg-black/50 backdrop-blur-xs transition-opacity"
        ></div>

        <div class="fixed inset-y-0 right-0 flex max-w-full pl-10">
            <!-- Slide-over panel -->
            <div 
                x-show="show"
                x-transition:enter="transform transition ease-out duration-300"
                x-transition:enter-start="translate-x-full"
                x-transition:enter-end="translate-x-0"
                x-transition:leave="transform transition ease-in duration-200"
                x-transition:leave-start="translate-x-0"
                x-transition:leave-end="translate-x-full"
                class="w-screen {{ $maxWidthClass }} bg-surface border-l border-border shadow-2xl flex flex-col justify-between overflow-hidden"
                x-on:click.stop
            >
                <!-- Offcanvas Header -->
                <div class="px-6 py-4.5 border-b border-border bg-canvas/40 flex items-center justify-between shrink-0">
                    <div>
                        <h3 class="text-sm font-bold text-ink flex items-center gap-2">
                            @if(isset($headerIcon))
                                {{ $headerIcon }}
                            @endif
                            <span>{{ $title }}</span>
                        </h3>
                        @if($subtitle)
                            <p class="text-[11px] text-muted mt-0.5">{{ $subtitle }}</p>
                        @endif
                    </div>
                    <button 
                        type="button" 
                        x-on:click="close()" 
                        class="p-1.5 rounded-lg text-muted hover:text-ink hover:bg-canvas transition focus:outline-none"
                        aria-label="Close panel"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <!-- Offcanvas Body -->
                <div class="p-6 overflow-y-auto sidebar-scroll flex-1 space-y-6">
                    {{ $slot }}
                </div>

                @if(isset($footer))
                    <!-- Offcanvas Footer -->
                    <div class="px-6 py-3.5 border-t border-border bg-canvas/40 flex items-center justify-end gap-3 shrink-0">
                        {{ $footer }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</template>
