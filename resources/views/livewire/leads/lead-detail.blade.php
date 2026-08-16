<div>
    @if($isOpen && $lead)
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-black/40 z-50 transition-opacity" wire:click="close"></div>

        <!-- Slide-over Container -->
        <div class="fixed inset-y-0 right-0 max-w-2xl w-full bg-surface shadow-2xl z-50 overflow-y-auto flex flex-col justify-between border-l border-border">
            <!-- Header -->
            <div class="p-6 border-b border-border flex items-center justify-between bg-canvas">
                <div>
                    <div class="flex items-center space-x-2">
                        <span class="px-2 py-0.5 text-[10px] font-bold rounded-pill bg-ink text-white">{{ $lead->lead_code }}</span>
                        <x-ui.badge status="{{ $lead->current_stage }}">{{ ucfirst(str_replace('_', ' ', $lead->current_stage)) }}</x-ui.badge>
                    </div>
                    <h2 class="text-xl font-bold text-ink mt-1">{{ $lead->name }}</h2>
                    <p class="text-xs text-muted">{{ $lead->city }} • {{ $lead->property_type }} • ₹{{ number_format($lead->budget / 100000, 1) }}L</p>
                </div>
                <button wire:click="close" class="text-muted hover:text-ink p-2 rounded-lg hover:bg-surface">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Body Content -->
            <div class="p-6 flex-1 space-y-6">


                <!-- Meta Specs Grid -->
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 p-4 bg-canvas rounded-card border border-border">
                    <div>
                        <div class="text-[10px] font-bold text-muted uppercase">Phone</div>
                        <div class="text-xs font-semibold text-ink mt-0.5">{{ $lead->mobile }}</div>
                    </div>
                    <div>
                        <div class="text-[10px] font-bold text-muted uppercase">Email</div>
                        <div class="text-xs font-semibold text-ink mt-0.5 truncate">{{ $lead->email ?: 'N/A' }}</div>
                    </div>
                    <div>
                        <div class="text-[10px] font-bold text-muted uppercase">Assigned Agent</div>
                        <div class="text-xs font-semibold text-ink mt-0.5">{{ $lead->assignedTo?->name ?: 'Unassigned' }}</div>
                    </div>
                    <div>
                        <div class="text-[10px] font-bold text-muted uppercase">Project</div>
                        <div class="text-xs font-semibold text-ink mt-0.5">{{ $lead->project?->name }}</div>
                    </div>
                    <div>
                        <div class="text-[10px] font-bold text-muted uppercase">Campaign</div>
                        <div class="text-xs font-semibold text-ink mt-0.5">{{ $lead->campaign?->name ?: 'Direct' }}</div>
                    </div>
                    <div>
                        <div class="text-[10px] font-bold text-muted uppercase">Lead Score</div>
                        <div class="text-xs font-semibold text-ink mt-0.5">{{ $lead->lead_score }} / 100</div>
                    </div>
                </div>

                <!-- Navigation Tabs -->
                <div class="border-b border-border flex space-x-6 text-sm font-medium">
                    <button wire:click="$set('activeTab', 'timeline')" class="pb-2 border-b-2 {{ $activeTab === 'timeline' ? 'border-ink text-ink font-bold' : 'border-transparent text-muted hover:text-ink' }}">
                        Timeline & Audit Log
                    </button>
                    <button wire:click="$set('activeTab', 'actions')" class="pb-2 border-b-2 {{ $activeTab === 'actions' ? 'border-ink text-ink font-bold' : 'border-transparent text-muted hover:text-ink' }}">
                        Quick Actions
                    </button>
                    <button wire:click="$set('activeTab', 'communications')" class="pb-2 border-b-2 {{ $activeTab === 'communications' ? 'border-ink text-ink font-bold' : 'border-transparent text-muted hover:text-ink' }}">
                        Communications ({{ count($communications) }})
                    </button>
                </div>

                <!-- Tab 1: Timeline -->
                @if($activeTab === 'timeline')
                    <div class="space-y-4">
                        <h4 class="text-xs font-bold text-muted uppercase tracking-wider">Stage Transitions</h4>
                        <div class="space-y-3">
                            @forelse($statusHistory as $history)
                                <div class="flex items-start space-x-3 text-xs">
                                    <div class="h-2 w-2 rounded-full bg-accent mt-1.5 flex-shrink-0"></div>
                                    <div>
                                        <span class="font-semibold text-ink">Moved to {{ ucfirst(str_replace('_', ' ', $history->to_status)) }}</span>
                                        <span class="text-muted">from {{ ucfirst(str_replace('_', ' ', $history->from_status)) }}</span>
                                        <div class="text-[10px] text-muted">{{ \Carbon\Carbon::parse($history->changed_at)->diffForHumans() }}</div>
                                    </div>
                                </div>
                            @empty
                                <div class="text-xs text-muted">No status transitions logged yet.</div>
                            @endforelse
                        </div>
                    </div>
                @endif

                <!-- Tab 2: Quick Actions -->
                @if($activeTab === 'actions')
                    <div class="space-y-6">
                        <!-- Log Call -->
                        <div class="p-4 bg-canvas rounded-card border border-border space-y-3">
                            <h4 class="text-xs font-bold text-ink uppercase tracking-wider flex items-center space-x-2">
                                <svg class="w-4 h-4 text-ink" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                <span>Log Call</span>
                            </h4>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="text-[10px] font-semibold text-muted">Outcome</label>
                                    <input type="text" wire:model="callOutcome" class="w-full text-xs p-2 rounded-lg border border-border bg-surface text-ink">
                                </div>
                                <div>
                                    <label class="text-[10px] font-semibold text-muted">Duration (Mins)</label>
                                    <input type="number" wire:model="callDuration" class="w-full text-xs p-2 rounded-lg border border-border bg-surface text-ink">
                                </div>
                            </div>
                            <x-ui.button wire:click="logCall" variant="primary" class="w-full text-xs">Save Call Log</x-ui.button>
                        </div>

                        <!-- Schedule Meeting -->
                        <div class="p-4 bg-canvas rounded-card border border-border space-y-3">
                            <h4 class="text-xs font-bold text-ink uppercase tracking-wider flex items-center space-x-2">
                                <svg class="w-4 h-4 text-ink" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <span>Schedule Meeting</span>
                            </h4>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="text-[10px] font-semibold text-muted">Date & Time</label>
                                    <input type="datetime-local" wire:model="meetingScheduledAt" class="w-full text-xs p-2 rounded-lg border border-border bg-surface text-ink">
                                </div>
                                <div>
                                    <label class="text-[10px] font-semibold text-muted">Location</label>
                                    <input type="text" wire:model="meetingLocation" placeholder="Site Office" class="w-full text-xs p-2 rounded-lg border border-border bg-surface text-ink">
                                </div>
                            </div>
                            <x-ui.button wire:click="scheduleMeeting" variant="primary" class="w-full text-xs">Schedule Meeting</x-ui.button>
                        </div>

                        <!-- Move to Replacement -->
                        <div class="p-4 bg-canvas rounded-card border border-border space-y-3">
                            <h4 class="text-xs font-bold text-danger uppercase tracking-wider flex items-center space-x-2">
                                <svg class="w-4 h-4 text-danger" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                <span>Request Lead Replacement</span>
                            </h4>
                            <div>
                                <label class="text-[10px] font-semibold text-muted">Select Reason</label>
                                <livewire:shared.searchable-select 
                                    :model="\App\Models\ReplacementReason::class"
                                    displayColumn="label"
                                    :searchable="true"
                                    placeholder="Select reason..."
                                    wire:model="replacementReasonId"
                                    key="detail-repl-reason"
                                />
                            </div>
                            <x-ui.button wire:click="requestReplacement" variant="danger" class="w-full text-xs">Submit Replacement Claim</x-ui.button>
                        </div>
                    </div>
                @endif

                <!-- Tab 3: Communications -->
                @if($activeTab === 'communications')
                    <div class="space-y-3">
                        @forelse($communications as $comm)
                            <div class="p-3 bg-canvas rounded-lg border border-border space-y-1">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-bold text-ink uppercase">{{ $comm->channel }}</span>
                                    <span class="text-[10px] text-muted">{{ \Carbon\Carbon::parse($comm->sent_at)->format('M d, H:i') }}</span>
                                </div>
                                <p class="text-xs text-muted">{{ $comm->message }}</p>
                            </div>
                        @empty
                            <div class="text-xs text-muted">No communications recorded yet.</div>
                        @endforelse
                    </div>
                @endif
            </div>

            <!-- Footer -->
            <div class="p-4 border-t border-border bg-canvas text-right">
                <x-ui.button wire:click="close" variant="secondary" class="text-xs">Close Panel</x-ui.button>
            </div>
        </div>
    @endif
</div>
