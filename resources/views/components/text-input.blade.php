@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-border focus:outline-none focus:border-transparent focus:ring-2 focus:ring-ink rounded-lg bg-surface text-ink text-sm shadow-sm transition']) }}>
