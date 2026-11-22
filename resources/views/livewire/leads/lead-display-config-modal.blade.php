<div>
    @if($isOpen)
    <div class="fixed inset-0 z-50 overflow-y-auto bg-ink/50 backdrop-blur-xs flex items-center justify-center p-4"
         wire:keydown.escape="close">
        <div class="bg-surface rounded-2xl border border-border shadow-2xl max-w-xl w-full p-6 space-y-4 max-h-[calc(100dvh-2rem)] overflow-y-auto overscroll-contain animate-in fade-in zoom-in-95 duration-150">
            
            <!-- Modal Header -->
            <div class="flex items-start justify-between pb-4 border-b border-border">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-bold text-ink">Configure Lead Details Display</h3>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200">
                                {{ $organizationName }}
                            </span>
                        </div>
                        <p class="text-xs text-muted mt-0.5">
                            Customize which fields and sections appear in this organization's Lead Details drawer.
                        </p>
                    </div>
                </div>
                <button type="button" wire:click="close" class="text-muted hover:text-ink p-1 rounded-lg hover:bg-canvas transition cursor-pointer">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Quick Action Bar -->
            <div class="flex items-center justify-between text-xs py-1">
                <span class="text-muted font-medium">Select fields to show in the drawer:</span>
                <div class="flex items-center gap-2">
                    <button type="button" wire:click="selectAll"
                        class="text-xs font-semibold text-primary hover:text-primary-dark transition cursor-pointer">
                        Select All
                    </button>
                    <span class="text-muted">&bull;</span>
                    <button type="button" wire:click="deselectAll"
                        class="text-xs font-semibold text-muted hover:text-ink transition cursor-pointer">
                        Deselect All
                    </button>
                </div>
            </div>

            <!-- Fields List -->
            <div class="space-y-2.5 max-h-[55vh] overflow-y-auto pr-1">
                @php
                    $categories = [
                        'Customer Info' => ['phone', 'email', 'city'],
                        'Project & Budget' => ['project', 'property_type', 'budget'],
                        'Requirements & Notes' => ['requirement'],
                        'Meta Ads Intelligence' => ['meta_submission', 'meta_context'],
                    ];
                @endphp

                @foreach($categories as $categoryName => $keys)
                    <div class="pt-2 first:pt-0">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-muted block mb-1.5">
                            {{ $categoryName }}
                        </span>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            @foreach($keys as $key)
                                @php
                                    $def = $fieldDefinitions[$key];
                                    $checked = (bool)($config[$key] ?? false);
                                @endphp
                                <label class="flex items-start gap-2.5 p-2.5 rounded-xl border transition-all cursor-pointer {{ $checked ? 'bg-primary/5 border-primary/40 shadow-2xs' : 'bg-surface border-border hover:border-neutral-300' }}">
                                    <input type="checkbox"
                                           wire:model.live="config.{{ $key }}"
                                           class="mt-0.5 rounded border-neutral-300 text-primary focus:ring-primary h-4 w-4 shrink-0 cursor-pointer">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center justify-between gap-1">
                                            <span class="text-xs font-semibold {{ $checked ? 'text-ink' : 'text-muted' }}">
                                                {{ $def['label'] }}
                                            </span>
                                            @if(str_starts_with($key, 'meta_'))
                                                <span class="text-[9px] font-bold px-1.5 py-0.2 rounded bg-blue-50 text-blue-600 border border-blue-200">
                                                    Meta
                                                </span>
                                            @endif
                                        </div>
                                        <p class="text-[10px] text-muted leading-tight mt-0.5">
                                            {{ $def['description'] }}
                                        </p>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Modal Footer -->
            <div class="flex items-center justify-between pt-4 border-t border-border">
                <span class="text-[11px] text-muted">
                    Preferences are isolated strictly to <strong>{{ $organizationName }}</strong>.
                </span>
                <div class="flex items-center gap-2">
                    <button type="button" wire:click="close"
                        class="px-4 py-2 rounded-lg border border-border bg-canvas text-ink text-xs font-semibold hover:bg-neutral-200/60 transition cursor-pointer">
                        Cancel
                    </button>
                    <button type="button" wire:click="save"
                        class="px-4 py-2 rounded-lg bg-primary hover:bg-primary-dark text-white text-xs font-semibold transition shadow-2xs cursor-pointer inline-flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>Save Preferences</span>
                    </button>
                </div>
            </div>

        </div>
    </div>
    @endif
</div>
