@props(['revealGenerated' => false])
<div class="relative" x-data="{ visible: false }"
    x-on:profile-password-generated.window="if (@js($revealGenerated)) visible = true"
    x-on:password-updated.window="visible = false">
    <input type="password" x-bind:type="visible ? 'text' : 'password'"
        {{ $attributes->merge(['class' => 'w-full h-9 pl-3.5 pr-16 rounded-lg border border-border bg-canvas text-ink text-xs focus:outline-none focus:ring-2 focus:ring-ink focus:border-transparent transition font-medium placeholder:text-muted']) }}>
    <button type="button" x-on:click="visible = !visible" x-bind:aria-pressed="visible"
        x-bind:aria-label="visible ? 'Hide password' : 'Show password'"
        class="absolute right-1 top-1 bottom-1 px-2 rounded text-xs font-semibold text-muted hover:text-ink hover:bg-surface focus-visible:outline-2 focus-visible:outline-primary">
        <span x-text="visible ? 'Hide' : 'Show'">Show</span>
    </button>
</div>
