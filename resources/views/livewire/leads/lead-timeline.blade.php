<div>
    @if($isOpen && $journey)
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-black/60 backdrop-blur-xs z-50 transition-opacity" wire:click="close"></div>

        <!-- Full Journey Modal Container -->
        <div class="fixed inset-y-0 right-0 max-w-3xl w-full bg-surface shadow-2xl z-50 overflow-y-auto flex flex-col justify-between border-l border-border">
            <!-- Modal Header -->
            <div class="p-6 border-b border-border bg-canvas flex flex-col sm:flex-row sm:items-center justify-between gap-4 sticky top-0 z-10">
                <div>
                    <div class="flex items-center space-x-2">
                        <span class="px-2.5 py-0.5 text-xs font-bold font-mono rounded-pill bg-ink text-white shadow-2xs">
                            {{ $journey['summary']['lead_code'] }}
                        </span>
                        <span class="px-2.5 py-0.5 text-xs font-bold rounded-pill uppercase border bg-indigo-100 text-indigo-950 border-indigo-300">
                            {{ ucfirst(str_replace(['_', '-'], ' ', $journey['summary']['current_stage'])) }}
                        </span>
                        <span class="px-2.5 py-0.5 text-xs font-bold rounded-pill uppercase border {{ str_contains($journey['summary']['sla_badge'], 'Met') ? 'bg-emerald-100 text-emerald-950 border-emerald-300' : 'bg-red-100 text-red-950 border-red-300' }}">
                            {{ $journey['summary']['sla_badge'] }}
                        </span>
                    </div>
                    <h2 class="text-xl font-extrabold text-ink mt-1.5 flex items-center space-x-2">
                        <span>{{ $journey['summary']['name'] }}</span>
                        <span class="text-xs font-normal text-muted">— Full Lead Journey Timeline</span>
                    </h2>
                    <p class="text-xs text-muted mt-0.5">
                        Project: <span class="font-bold text-ink">{{ $journey['summary']['project_name'] }}</span> • 
                        Assigned To: <span class="font-bold text-ink">{{ $journey['summary']['assigned_to'] }}</span> • 
                        Cost: <span class="font-bold text-primary font-mono">{{ $journey['summary']['lead_cost'] }}</span>
                    </p>
                </div>

                <div class="flex items-center space-x-2">
                    <button wire:click="toggleSort" class="p-2 rounded-lg border border-border bg-surface text-ink hover:bg-canvas text-xs font-bold flex items-center space-x-1 cursor-pointer" title="Toggle Chronological Order">
                        <svg class="w-4 h-4 {{ $sortOrder === 'asc' ? 'rotate-180' : '' }} transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"/></svg>
                        <span>{{ $sortOrder === 'desc' ? 'Newest First' : 'Oldest First' }}</span>
                    </button>
                    <button wire:click="close" class="p-2 text-muted hover:text-ink rounded-lg hover:bg-surface border border-transparent hover:border-border cursor-pointer">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>

            <!-- Filter Chips Bar -->
            <div class="px-6 py-3 bg-surface border-b border-border flex items-center gap-1.5 overflow-x-auto text-xs">
                <span class="font-bold text-muted mr-1">Milestones:</span>
                @php
                    $filters = [
                        'all' => 'All Events',
                        'status' => 'Stages',
                        'assignment' => 'Assignments',
                        'credit' => 'Credits',
                        'sla' => 'SLA',
                        'replacement' => 'Replacements',
                        'communication' => 'Communications',
                        'call' => 'Calls',
                    ];
                @endphp
                @foreach($filters as $key => $label)
                    <button 
                        wire:click="setFilter('{{ $key }}')" 
                        class="px-2.5 py-1 rounded-full font-bold text-xs whitespace-nowrap transition cursor-pointer {{ $filterType === $key ? 'bg-ink text-white shadow-2xs' : 'bg-canvas text-muted hover:text-ink border border-border' }}"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            <!-- Timeline Body -->
            <div class="p-6 flex-1 space-y-6 bg-canvas">
                @if(empty($journey['events']))
                    <div class="text-center py-12 bg-surface rounded-card border border-border p-6 text-muted space-y-2">
                        <svg class="w-10 h-10 mx-auto text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <div class="font-bold text-sm text-ink">No events found for this filter</div>
                        <p class="text-xs">Try selecting 'All Events' to view the full journey.</p>
                    </div>
                @else
                    <div class="relative pl-6 space-y-6 before:absolute before:left-3 before:top-3 before:bottom-3 before:w-0.5 before:bg-border">
                        @foreach($journey['events'] as $event)
                            <div class="relative group">
                                <!-- Dot Marker -->
                                <div class="absolute -left-6 top-1.5 w-6 h-6 rounded-full bg-surface border-2 border-primary flex items-center justify-center shadow-xs">
                                    <div class="w-2 h-2 rounded-full bg-primary"></div>
                                </div>

                                <!-- Card -->
                                <div class="p-4 bg-surface rounded-xl border border-border shadow-2xs space-y-2 hover:border-gray-400 transition">
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-border/60 pb-2">
                                        <div class="flex items-center space-x-2">
                                            <span class="px-2 py-0.5 text-[10px] font-bold uppercase rounded border {{ $event['color'] }}">
                                                {{ $event['badge'] }}
                                            </span>
                                            <span class="text-xs font-bold text-ink">{{ $event['title'] }}</span>
                                        </div>
                                        <div class="text-[11px] font-mono text-muted flex items-center space-x-1.5">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            <span>{{ $event['timestamp']->format('d M Y, h:i A') }}</span>
                                            <span class="text-gray-300">•</span>
                                            <span>{{ $event['timestamp']->diffForHumans() }}</span>
                                        </div>
                                    </div>

                                    <p class="text-xs text-muted leading-relaxed">
                                        {{ $event['details'] }}
                                    </p>

                                    <div class="flex items-center justify-between pt-1 text-[11px] text-muted">
                                        <div class="flex items-center space-x-1">
                                            <span class="font-semibold text-gray-500">Source:</span>
                                            <span class="font-medium text-ink">{{ $event['actor'] ?? 'System' }}</span>
                                        </div>
                                        <div class="text-[10px] font-bold uppercase tracking-wider text-gray-400">
                                            {{ $event['category'] }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Footer -->
            <div class="p-4 bg-canvas border-t border-border flex items-center justify-between text-xs text-muted">
                <div>
                    Displaying <span class="font-bold text-ink">{{ count($journey['events']) }}</span> journey milestone(s).
                </div>
                <button wire:click="close" class="px-4 py-2 bg-ink text-white font-bold rounded-lg hover:bg-neutral-800 transition cursor-pointer">
                    Close Journey
                </button>
            </div>
        </div>
    @endif
</div>
