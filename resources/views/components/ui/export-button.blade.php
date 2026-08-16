@props([
    'target' => 'exportExcel',
    'label' => 'Export to Excel',
    'variant' => 'secondary',
    'class' => '',
])

<button 
    type="button"
    wire:click="{{ $target }}"
    wire:loading.attr="disabled"
    wire:target="{{ $target }}"
    {{ $attributes->merge(['class' => 'h-8 border border-border bg-white rounded-lg px-3.5 text-xs font-semibold text-ink hover:bg-canvas transition flex items-center gap-2 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer ' . $class]) }}
>
    <!-- Idle Icon (Spreadsheet) -->
    <svg wire:loading.remove wire:target="{{ $target }}" class="w-4 h-4 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
    </svg>

    <!-- Loading Spinner Icon -->
    <svg wire:loading wire:target="{{ $target }}" class="animate-spin w-4 h-4 text-ink flex-shrink-0" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
    </svg>

    <!-- Label Text -->
    <span wire:loading.remove wire:target="{{ $target }}">{{ $label }}</span>
    <span wire:loading wire:target="{{ $target }}">Exporting...</span>
</button>
