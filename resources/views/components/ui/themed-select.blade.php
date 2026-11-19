@props([
    'options' => [],
    'placeholder' => 'Select Option',
    'class' => 'w-full',
    'triggerClass' => '',
    'searchable' => false,
    'align' => 'left',
])

@php
    $wireModel = $attributes->wire('model')->value();
    $alpineModel = $attributes->whereStartsWith('x-model');
    $nativeAttributes = $attributes->whereDoesntStartWith(['wire:model', 'x-model'])->except(['class', 'value']);
    $owner = isset($this) && $this instanceof \Livewire\Component ? $this->getId() : 'page';
    $popupKey = 'select-popup-'.sha1($owner.'|'.($wireModel ?? $alpineModel->first() ?? $attributes->get('id') ?? $attributes->get('name') ?? $placeholder));
@endphp
<div x-id="['select-list', 'select-error']" x-modelable="value" {{ $alpineModel }}
    x-data="leadPantherSelect({
        value: @if($wireModel) $wire.entangle(@js($wireModel)){{ $attributes->wire('model')->hasModifier('live') ? '.live' : '' }} @else @js($attributes->get('value')) @endif,
        bound: @js((bool)$wireModel || count($alpineModel->getAttributes()) > 0 || $attributes->has('value')),
        placeholder: @js($placeholder), align: @js($align)
    })"
    @keydown.escape.stop.prevent="close(true)" @keydown.tab="close()"
    @resize.window="if(open) place()" @scroll.window.capture="if(open && !$refs.panel.contains($event.target)) place()"
    x-on:livewire:navigating.window="close()"
    class="relative inline-block min-w-0 max-w-full text-left text-xs {{ $class }}">
    {{-- Retains HTML form semantics and original change/disabled/required attributes. --}}
    <select x-ref="native" {{ $nativeAttributes }} class="sr-only" tabindex="-1" aria-hidden="true"
        @invalid.prevent="invalid()" @focus="$refs.trigger.focus()">
        @if(trim((string)$slot) !== '')
            {{ $slot }}
        @else
            @foreach($options as $key => $option)
                @php
                    $optionValue = is_array($option) ? ($option['value'] ?? $key) : $key;
                    $optionLabel = is_array($option) ? ($option['label'] ?? $option['name'] ?? $optionValue) : $option;
                @endphp
                <option value="{{ $optionValue }}" @disabled(is_array($option) && ($option['disabled'] ?? false))>{{ $optionLabel }}</option>
            @endforeach
        @endif
    </select>
    <button x-ref="trigger" type="button" @click="open ? close() : show()" @keydown.arrow-down.prevent="show()"
        :disabled="disabled" :aria-label="accessibleLabel" :aria-expanded="open" :aria-controls="$id('select-list')"
        :aria-invalid="Boolean(validationMessage)" :aria-describedby="validationMessage ? $id('select-error') : null" aria-haspopup="listbox"
        class="lp-select-trigger inline-flex w-full min-w-0 items-center justify-between gap-2 h-9 px-3 rounded-lg border border-border bg-canvas text-ink text-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary disabled:opacity-50 disabled:cursor-not-allowed {{ $triggerClass }}">
        <span class="truncate text-left font-medium" x-text="selectedLabel"></span>
        <svg class="w-4 h-4 shrink-0 text-muted" :class="open && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
    </button>
    <p x-show="validationMessage" x-cloak :id="$id('select-error')" x-text="validationMessage" class="mt-1 text-xs text-danger"></p>
    <template x-teleport="body">
        <div wire:key="{{ $popupKey }}" x-ref="panel" @click.stop x-show="open" x-cloak :style="panelStyle" @click.outside="if (!$refs.trigger.contains($event.target)) close()"
            @keydown.escape.stop.prevent="close(true)" @keydown.tab="close()" @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)" @keydown.enter.prevent="select(filteredOptions[activeIndex])"
            tabindex="-1" role="listbox" :id="$id('select-list')" :aria-label="accessibleLabel"
            class="lp-select-panel fixed z-[100] overflow-y-auto overscroll-contain rounded-card border border-border bg-surface p-1.5 shadow-xl text-xs text-ink focus:outline-none">
            @if($searchable)
                <input x-ref="searchInput" x-model="search" @input="activeIndex=0" type="search" placeholder="Search options..." aria-label="Search options" class="sticky top-0 mb-1 w-full rounded-lg border-border bg-canvas text-ink text-sm" />
            @endif
            <template x-for="(option, index) in filteredOptions" :key="option.value">
                <button type="button" role="option" :aria-selected="key(value) === option.value" :disabled="option.disabled"
                    @click="select(option)" @mouseenter="activeIndex=index"
                    :class="index === activeIndex || key(value) === option.value ? 'bg-canvas font-semibold' : ''"
                    class="flex w-full min-w-0 items-center justify-between gap-3 rounded-lg px-3 py-2 text-left text-ink hover:bg-canvas disabled:opacity-40 disabled:cursor-not-allowed">
                    <span class="break-words" x-text="option.label"></span>
                    <svg x-show="key(value) === option.value" class="w-4 h-4 shrink-0 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                </button>
            </template>
            <p x-show="!filteredOptions.length" class="p-3 text-center text-muted">No matching results</p>
        </div>
    </template>
</div>
