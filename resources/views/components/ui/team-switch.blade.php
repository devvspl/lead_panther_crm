@props(['label'])
<label class="inline-flex items-center gap-2 cursor-pointer text-xs text-ink">
    <input type="checkbox" role="switch" {{ $attributes->merge(['class' => 'sr-only peer']) }}>
    <span aria-hidden="true" class="relative h-5 w-9 shrink-0 rounded-full bg-muted/30 transition peer-checked:bg-primary peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-primary after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:h-4 after:w-4 after:rounded-full after:bg-surface after:shadow-sm after:transition-transform peer-checked:after:translate-x-4"></span>
    <span>{{ $label }}</span>
</label>
