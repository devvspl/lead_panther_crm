<div class="space-y-6">
    <!-- Top Filter Bar -->
    <div class="bg-surface rounded-card border border-border p-4 shadow-sm flex flex-col md:flex-row md:items-center md:justify-between gap-4">
         <!-- Filter Controls -->
        <div class="flex flex-wrap items-center gap-3">
            <!-- Search -->
            <div class="relative w-44 sm:w-56">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search leads..." class="w-full pl-8 pr-3 py-1.5 bg-canvas text-ink text-xs rounded-lg border border-border focus:ring-2 focus:ring-ink focus:bg-surface">
                <svg class="w-3.5 h-3.5 text-muted absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>

            <!-- Sort By Filter -->
            <x-ui.themed-select 
                wire:model.live="sort_by"
                searchable="true"
                :options="[
                    'created_desc' => 'Latest Created',
                    'score_desc' => 'Score: High to Low',
                    'score_asc' => 'Score: Low to High'
                ]"
                placeholder="Sort By"
            />

            <!-- Project Filter -->
            <livewire:shared.searchable-select 
                :model="\App\Models\Project::class"
                :searchable="true"
                placeholder="All Projects"
                wire:model.live="project"
                key="select-project"
            />

            <!-- Sales Exec Filter -->
            <livewire:shared.searchable-select 
                :model="\App\Models\User::class"
                roleFilter="sales-executive, Sales Executive"
                :searchable="true"
                placeholder="All Agents"
                wire:model.live="executive"
                key="select-executive"
            />

            <!-- Lead Source Filter -->
            <livewire:shared.searchable-select 
                :model="\App\Models\LeadSource::class"
                :searchable="true"
                placeholder="All Sources"
                wire:model.live="source"
                key="select-source"
            />

            <!-- SLA Breached Toggle -->
            <label class="flex items-center space-x-1.5 text-xs text-ink cursor-pointer bg-canvas px-2.5 py-1 rounded-lg border border-border select-none">
                <input type="checkbox" wire:model.live="sla_breached" class="rounded text-ink focus:ring-ink">
                <span class="font-medium text-danger">SLA Breached Only</span>
            </label>

            <x-ui.export-button target="exportExcel" />
        </div>
    </div>

    <!-- Confirmation Modal for Terminal / Exception Stage Transition -->
    @if($showConfirmModal)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
            <div class="bg-surface rounded-card border border-border p-6 max-w-md w-full shadow-2xl space-y-4">
                <div class="flex items-center space-x-3 text-warning">
                    <svg class="w-6 h-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <h3 class="text-base font-bold text-ink">Confirm Stage Change</h3>
                </div>
                <p class="text-xs text-muted">
                    Moving a lead to <span class="font-bold text-ink">{{ ucfirst(str_replace('_', ' ', $pendingToStage)) }}</span> is an exception/terminal transition. Are you sure you want to proceed?
                </p>
                <div class="flex items-center justify-end space-x-3 pt-2">
                    <x-ui.button wire:click="cancelTerminalTransition" variant="secondary" class="text-xs">Cancel</x-ui.button>
                    <x-ui.button wire:click="confirmTerminalTransition" variant="danger" class="text-xs">Confirm Move</x-ui.button>
                </div>
            </div>
        </div>
    @endif

    <!-- Kanban Board Grid (Horizontal Scrollable Container) -->
    <div class="overflow-x-auto pb-6">
        <div class="flex items-start space-x-4 min-w-max">
            <!-- Main Flow Columns -->
            @foreach($mainStages as $stageKey => $stageLabel)
                <div class="w-72 bg-canvas/60 rounded-card border border-border p-3 flex flex-col max-h-[calc(100vh-220px)] flex-shrink-0">
                    <!-- Column Header -->
                    <div class="flex items-center justify-between pb-3 mb-3 border-b border-border">
                        <div class="flex items-center space-x-2">
                            <span class="w-2.5 h-2.5 rounded-full {{ in_array($stageKey, ['new', 'assigned']) ? 'bg-blue-500' : (in_array($stageKey, ['contacted', 'connected', 'qualified']) ? 'bg-amber-500' : (in_array($stageKey, ['closed_won', 'booking', 'payment']) ? 'bg-green-500' : 'bg-purple-500')) }}"></span>
                            <h3 class="text-xs font-bold text-ink tracking-tight">{{ $stageLabel }}</h3>
                        </div>
                        <span class="px-2 py-0.5 text-[10px] font-bold rounded-pill bg-surface text-ink border border-border shadow-2xs">
                            {{ $stageCounts[$stageKey] ?? 0 }}
                        </span>
                    </div>

                    <!-- Cards Droppable Container -->
                    <div 
                        data-stage-key="{{ $stageKey }}"
                        x-data="{ 
                            initSortable() { 
                                new Sortable($el, { 
                                    group: 'kanban', 
                                    animation: 150, 
                                    ghostClass: 'opacity-50',
                                    onEnd: (evt) => { 
                                        const leadId = evt.item.getAttribute('data-lead-id'); 
                                        const toStage = evt.to.getAttribute('data-stage-key'); 
                                        $wire.updateLeadStage(leadId, toStage); 
                                    } 
                                }); 
                            } 
                        }" 
                        x-init="initSortable()"
                        class="flex-1 overflow-y-auto space-y-3 pr-1 min-h-[150px]"
                    >
                        @forelse($stageLeads[$stageKey] ?? [] as $lead)
                            @php
                                $score = $lead->lead_score ?? 0;
                                $scoreBadgeClass = $score >= 70 
                                    ? 'bg-emerald-50 text-emerald-700 border-emerald-200' 
                                    : ($score >= 40 ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-slate-50 text-slate-600 border-slate-200');
                            @endphp

                            <div 
                                data-lead-id="{{ $lead->id }}"
                                @click="$dispatch('open-lead-detail', { id: {{ $lead->id }} })"
                                class="bg-surface rounded-xl border border-border p-3.5 shadow-2xs hover:shadow-md transition cursor-pointer space-y-2.5 group relative border-l-4 {{ $lead->first_response_at ? 'border-l-green-500' : (\Carbon\Carbon::parse($lead->created_at)->diffInMinutes(now()) > 15 ? 'border-l-red-500' : 'border-l-amber-500') }}"
                            >
                                <!-- Top Bar inside Card -->
                                <div class="flex items-center justify-between text-[10px]">
                                    <div class="flex items-center space-x-1.5">
                                        <span class="font-bold text-muted tracking-tight">{{ $lead->lead_code }}</span>
                                        <!-- Score Progress Badge -->
                                        <span class="px-1.5 py-0.5 rounded-full text-[9px] font-extrabold border {{ $scoreBadgeClass }}" title="Lead Priority Score: {{ $score }}/100">
                                            ⚡ {{ $score }}
                                        </span>
                                    </div>

                                    <!-- SLA Pill -->
                                    @if($lead->first_response_at)
                                        <span class="px-1.5 py-0.5 rounded-pill bg-green-50 text-green-700 font-semibold text-[9px]">SLA Met</span>
                                    @elseif(\Carbon\Carbon::parse($lead->created_at)->diffInMinutes(now()) > 15)
                                        <span class="px-1.5 py-0.5 rounded-pill bg-red-50 text-red-700 font-bold text-[9px]">SLA Breached</span>
                                    @else
                                        <span class="px-1.5 py-0.5 rounded-pill bg-amber-50 text-amber-700 font-semibold text-[9px]">SLA Pending</span>
                                    @endif
                                </div>

                                <!-- Lead Name & City -->
                                <div>
                                    <h4 class="text-xs font-bold text-ink group-hover:text-info transition truncate">{{ $lead->name }}</h4>
                                    <p class="text-[11px] text-muted truncate">{{ $lead->city ?: 'Location N/A' }} • {{ $lead->property_type ?: 'Property' }}</p>
                                </div>

                                <!-- Budget & Source -->
                                <div class="flex items-center justify-between pt-1">
                                    <span class="text-xs font-extrabold text-ink">
                                        ₹{{ number_format($lead->budget / 100000, 1) }}L
                                    </span>
                                    <span class="px-2 py-0.5 text-[9px] font-bold uppercase rounded bg-canvas text-muted border border-border">
                                        {{ $lead->leadSource?->name ?: 'Direct' }}
                                    </span>
                                </div>

                                <!-- Bottom Row: Avatar & Days in Stage -->
                                <div class="flex items-center justify-between border-t border-border pt-2 text-[10px] text-muted">
                                    <div class="flex items-center space-x-1.5 min-w-0">
                                        <div class="h-5 w-5 rounded-full bg-accent text-white flex items-center justify-center font-bold text-[9px] flex-shrink-0">
                                            {{ strtoupper(substr($lead->assignedTo?->name ?? 'A', 0, 1)) }}
                                        </div>
                                        <span class="truncate font-medium text-ink">{{ $lead->assignedTo?->name ?: 'Unassigned' }}</span>
                                    </div>
                                    <span class="font-semibold text-muted flex-shrink-0">
                                        {{ \Carbon\Carbon::parse($lead->created_at)->diffInDays(now()) }}d
                                    </span>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-6 text-xs text-muted border-2 border-dashed border-border rounded-xl">
                                No leads in {{ $stageLabel }}
                            </div>
                        @endforelse
                    </div>

                    <!-- Pagination Load More Button -->
                    @if(($stageCounts[$stageKey] ?? 0) > count($stageLeads[$stageKey] ?? []))
                        <div class="pt-2 text-center">
                            <button wire:click="loadMore('{{ $stageKey }}')" class="text-[11px] font-bold text-muted hover:text-ink transition">
                                Load More (+20)
                            </button>
                        </div>
                    @endif
                </div>
            @endforeach

            <!-- Collapsed / Expandable "Other" Column at Far Right -->
            <div class="w-72 bg-canvas/60 rounded-card border border-border p-3 flex flex-col max-h-[calc(100vh-220px)] flex-shrink-0">
                <div class="flex items-center justify-between pb-3 mb-3 border-b border-border">
                    <div class="flex items-center space-x-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-gray-400"></span>
                        <h3 class="text-xs font-bold text-ink tracking-tight">Other / Closed</h3>
                    </div>
                    <button wire:click="$toggle('otherColumnExpanded')" class="text-xs text-muted hover:text-ink font-semibold">
                        {{ $otherColumnExpanded ? 'Collapse' : 'Expand' }}
                    </button>
                </div>

                @if($otherColumnExpanded)
                    <div class="space-y-4 overflow-y-auto flex-1">
                        @foreach($otherStages as $oStageKey => $oStageLabel)
                            <div class="border-t border-border pt-2">
                                <div class="text-[10px] font-bold text-muted uppercase mb-2 flex items-center justify-between">
                                    <span>{{ $oStageLabel }}</span>
                                    <span class="px-1.5 py-0.5 rounded bg-surface border border-border">{{ $stageCounts[$oStageKey] ?? 0 }}</span>
                                </div>
                                <div 
                                    data-stage-key="{{ $oStageKey }}"
                                    x-data="{ initSortable() { new Sortable($el, { group: 'kanban', animation: 150, onEnd: (evt) => { const leadId = evt.item.getAttribute('data-lead-id'); const toStage = evt.to.getAttribute('data-stage-key'); $wire.updateLeadStage(leadId, toStage); } }); } }"
                                    x-init="initSortable()"
                                    class="space-y-2 min-h-[60px]"
                                >
                                    @foreach($stageLeads[$oStageKey] ?? [] as $oLead)
                                        <div data-lead-id="{{ $oLead->id }}" @click="$dispatch('open-lead-detail', { id: {{ $oLead->id }} })" class="p-2 bg-surface rounded-lg border border-border text-xs cursor-pointer hover:bg-canvas">
                                            <div class="font-bold text-ink flex items-center justify-between">
                                                <span>{{ $oLead->name }}</span>
                                                <span class="text-[9px] px-1 rounded bg-canvas font-mono font-bold">⚡ {{ $oLead->lead_score ?? 0 }}</span>
                                            </div>
                                            <div class="text-[10px] text-muted">{{ $oLead->lead_code }} • ₹{{ number_format($oLead->budget / 100000, 1) }}L</div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="flex-1 flex flex-col items-center justify-center text-center p-6 text-muted space-y-2">
                        <svg class="w-8 h-8 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                        <p class="text-xs">Contains Closed Lost, Wrong Number, Duplicate, Rental, Replaced</p>
                        <button wire:click="$set('otherColumnExpanded', true)" class="text-xs font-bold text-ink underline">
                            View {{ array_sum(array_intersect_key($stageCounts, $otherStages)) }} Leads
                        </button>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Include Slide-over Detail Component -->
    <livewire:leads.lead-detail />

    <!-- CDN Script for SortableJS -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
</div>
