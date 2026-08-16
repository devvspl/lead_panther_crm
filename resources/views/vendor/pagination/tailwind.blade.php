@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination Navigation" class="flex items-center justify-between px-4 py-3 sm:px-6 border-t border-border">
        <!-- Results Summary -->
        <div class="text-xs text-muted">
            Showing <span class="font-semibold text-ink">{{ $paginator->firstItem() ?? 0 }}</span> to <span class="font-semibold text-ink">{{ $paginator->lastItem() ?? 0 }}</span> of <span class="font-semibold text-ink">{{ $paginator->total() }}</span> results
        </div>

        <!-- Buttons Container -->
        <div class="flex items-center space-x-1.5 text-xs">
            <!-- Previous Page Link -->
            @if ($paginator->onFirstPage())
                <span class="w-9 h-9 flex items-center justify-center rounded-lg border border-border text-muted opacity-40 cursor-not-allowed">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </span>
            @else
                <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" wire:loading.attr="disabled" class="w-9 h-9 flex items-center justify-center rounded-lg border border-border text-ink hover:bg-canvas transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </button>
            @endif

            <!-- Pagination Elements -->
            @foreach ($elements as $element)
                <!-- "Three Dots" Separator -->
                @if (is_string($element))
                    <span class="w-9 h-9 flex items-center justify-center text-xs text-muted font-medium">{{ $element }}</span>
                @endif

                <!-- Array Of Links -->
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="w-9 h-9 flex items-center justify-center rounded-lg text-xs font-bold bg-ink text-white shadow-sm">
                                {{ $page }}
                            </span>
                        @else
                            <button type="button" wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')" class="w-9 h-9 flex items-center justify-center rounded-lg text-xs font-medium text-muted hover:text-ink hover:bg-canvas transition">
                                {{ $page }}
                            </button>
                        @endif
                    @endforeach
                @endif
            @endforeach

            <!-- Next Page Link -->
            @if ($paginator->hasMorePages())
                <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" wire:loading.attr="disabled" class="w-9 h-9 flex items-center justify-center rounded-lg border border-border text-ink hover:bg-canvas transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>
            @else
                <span class="w-9 h-9 flex items-center justify-center rounded-lg border border-border text-muted opacity-40 cursor-not-allowed">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </span>
            @endif
        </div>
    </nav>
@endif
