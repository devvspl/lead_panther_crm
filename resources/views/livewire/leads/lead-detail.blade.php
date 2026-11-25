<div>
    @if($isOpen && $lead)
    <!-- Backdrop -->
    <div class="fixed inset-0 bg-ink/50 backdrop-blur-xs z-50 transition-opacity" wire:click="close"></div>

    <!-- Slide-over Drawer -->
    <div
        class="fixed inset-y-0 right-0 max-w-2xl w-full bg-surface shadow-2xl z-50 overflow-y-auto flex flex-col justify-between border-l border-border">
        <!-- 1. DRAWER HEADER -->
        <div class="p-5 border-b border-border flex items-center justify-between bg-canvas sticky top-0 z-10">
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2 flex-wrap">
                    <span
                        class="px-2 py-0.5 text-[10px] font-bold font-mono rounded bg-ink text-white">{{ $lead->lead_code }}</span>
                    <x-ui.badge
                        status="{{ $lead->current_stage }}">{{ ucfirst(str_replace('_', ' ', $lead->current_stage)) }}</x-ui.badge>
                    @if($lead->replacement_status === 'pending')
                        <span
                            class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-300">
                            Replacement Pending
                        </span>
                    @elseif($lead->replacement_status === 'rejected' || $latestReplacement?->status === 'rejected')
                        <span class="text-xs font-bold text-red-700">Rejected</span>
                    @elseif($lead->replacement_status === 'approved')
                        <span
                            class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-300">
                            Replacement Approved
                        </span>
                    @endif
                </div>
                @php
                    $displayConfig = $lead->effective_display_config ?? \App\Models\Organization::defaultLeadDisplayConfig();
                    $subtitleParts = [];
                    if ($displayConfig['project'] ?? true) {
                        $subtitleParts[] = $lead->project?->name ?: 'Direct';
                    }
                    if ($displayConfig['city'] ?? true) {
                        $subtitleParts[] = $lead->city ?: 'N/A';
                    }
                    $actualBudget = $lead->actual_budget ?: $lead->budget;
                    if (($displayConfig['budget'] ?? true) && $actualBudget !== null && $actualBudget > 0) {
                        $subtitleParts[] = \App\Support\LeadPropertyAndBudgetExtractor::formatShortBudget($actualBudget);
                    }
                @endphp
                <h2 class="text-lg font-bold text-ink mt-1 truncate">{{ $lead->name }}</h2>
                @if(!empty($subtitleParts))
                <p class="text-xs text-muted truncate">
                    {{ implode(' • ', $subtitleParts) }}
                </p>
                @endif
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <x-ui.lead-archive-menu :id="$lead->id" />
                @if(auth()->user()?->status === 'active' && auth()->user()->hasAnyRole(['Super Admin', 'super-admin']))
                <button type="button"
                    @click="$dispatch('open-super-admin-lead-modal', { id: {{ $lead->id }} }); if (window.Livewire) Livewire.dispatch('open-super-admin-lead-modal', { id: {{ $lead->id }} });"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold rounded-lg bg-purple-600 text-white hover:bg-purple-700 transition shadow-2xs cursor-pointer"
                    title="Master Inspector &amp; Full Lead Data Editor (Super Admin)">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    <span class="hidden sm:inline">Edit / All Data</span>
                </button>
                <button type="button" wire:click="showFullJourney"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 transition shadow-2xs cursor-pointer">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="hidden sm:inline">Lead Journey</span>
                </button>
                @endif
                <button type="button" wire:click="close"
                    class="text-muted hover:text-ink p-1.5 rounded-lg hover:bg-surface cursor-pointer">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- 2. DRAWER BODY CONTENT -->
        <div class="p-5 flex-1 space-y-5 text-xs">

            <!-- TABS NAVIGATION -->
            @if($lead->internalProcessing?->forwarded_at)
                <section class="rounded-xl border border-border bg-canvas p-4 space-y-2 mb-4">
                    <h3 class="text-sm font-semibold text-ink">Lead Panther Verification &amp; Handover</h3>
                    <div class="grid grid-cols-2 gap-2 text-xs text-ink">
                        <div>Verification: {{ ucfirst($lead->internalProcessing->verification_status) }}</div>
                        <div>Internal call attempts: {{ $lead->internalProcessing->call_attempts }}</div>
                        <div>Last contacted:
                            {{ $lead->internalProcessing->last_contacted_at?->format('M d, Y H:i') ?? 'Unavailable' }}</div>
                        <div>Handed over: {{ $lead->internalProcessing->forwarded_at->format('M d, Y H:i') }}</div>
                        <div>Assigned client executive: {{ $lead->assignedTo?->name ?? 'Unassigned' }}</div>
                        <div>Client stage: {{ ucwords(str_replace('_', ' ', $lead->current_stage)) }}</div>
                    </div>
                    <p class="text-xs text-muted font-semibold">Internal calling remarks</p>
                    <p class="text-xs text-ink whitespace-pre-wrap">
                        {{ $lead->internalProcessing->calling_remarks ?? 'No remarks recorded.' }}</p>
                    <details class="text-xs">
                        <summary class="text-primary cursor-pointer">Preserved internal call history</summary>
                        @foreach($lead->internalProcessing->events()->where('action', 'call')->orderBy('id')->get() as $internalCall)
                            <div class="border-t border-border py-2 mt-2"><span
                                    class="text-muted">{{ $internalCall->created_at->format('M d, Y H:i') }} ·
                                    {{ $internalCall->details['input']['calling_status'] ?? '' }}</span>
                                <p class="text-ink whitespace-pre-wrap">{{ $internalCall->details['input']['remarks'] ?? '' }}
                                </p>
                        </div>@endforeach
                    </details>
                </section>
            @endif
            <div class="border-b border-border flex space-x-6 font-semibold">
                <button type="button" wire:click="$set('activeTab', 'stage_details')"
                    class="pb-2.5 border-b-2 text-xs transition cursor-pointer {{ $activeTab === 'stage_details' ? 'border-primary text-primary font-bold' : 'border-transparent text-muted hover:text-ink' }}">
                    Stage Actions &amp; Lead Info
                </button>
                <button type="button" wire:click="$set('activeTab', 'timeline')"
                    class="pb-2.5 border-b-2 text-xs transition cursor-pointer {{ $activeTab === 'timeline' ? 'border-primary text-primary font-bold' : 'border-transparent text-muted hover:text-ink' }}">
                    Activity Timeline ({{ count($statusHistory) + count($leadCalls) }})
                </button>
                <button type="button" wire:click="$set('activeTab', 'communications')"
                    class="pb-2.5 border-b-2 text-xs transition cursor-pointer {{ $activeTab === 'communications' ? 'border-primary text-primary font-bold' : 'border-transparent text-muted hover:text-ink' }}">
                    Communications ({{ count($communications) }})
                </button>
            </div>

            @foreach($errors->all() as $error)
            <p class="text-xs text-red-700" role="alert">{{ $error }}</p>@endforeach
            @if($activeTab === 'stage_details')
            <!-- ========================================================= -->
            <!-- SECTION 1 & 2: CUSTOMER & PROJECT INFORMATION             -->
            <!-- ========================================================= -->
            @php
                $displayConfig = $lead->effective_display_config ?? \App\Models\Organization::defaultLeadDisplayConfig();
            @endphp
            <div class="p-3.5 bg-canvas rounded-xl border border-border space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold text-muted uppercase tracking-wider block">1 &bull; Customer &amp; Project Info</span>
                    <span class="text-[10px] text-muted font-medium">
                        Received: <strong class="text-ink font-semibold">{{ ($lead->delivered_at ?: $lead->created_at)?->timezone('Asia/Kolkata')->format('d M Y, h:i A') ?: '—' }}</strong>
                    </span>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 text-xs">
                    @if($displayConfig['phone'] ?? true)
                    <div>
                        <span class="text-[10px] text-muted block">Phone / Mobile</span>
                        <div class="flex items-center gap-1.5 mt-0.5">
                            <span class="font-mono font-semibold text-ink text-xs">{{ $lead->mobile }}</span>
                            <a href="tel:{{ $lead->mobile }}" class="text-primary hover:text-primary-dark transition"
                                title="Call Customer">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                </svg>
                            </a>
                            <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $lead->mobile) }}" target="_blank"
                                class="text-emerald-600 hover:text-emerald-700 transition" title="WhatsApp Chat">
                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z" />
                                </svg>
                            </a>
                        </div>
                    </div>
                    @endif
                    @if($displayConfig['email'] ?? true)
                    <div>
                        <span class="text-[10px] text-muted block">Email Address</span>
                        <span class="font-semibold text-ink truncate block mt-0.5 text-xs">{{ $lead->email ?: 'N/A' }}</span>
                    </div>
                    @endif
                    @if($displayConfig['city'] ?? true)
                    <div>
                        <span class="text-[10px] text-muted block">City / Location</span>
                        <span class="font-semibold text-ink block mt-0.5 text-xs">{{ $lead->city ?: 'N/A' }}
                            {{ $lead->actual_location ? '(' . $lead->actual_location . ')' : '' }}</span>
                    </div>
                    @endif
                    @if($displayConfig['project'] ?? true)
                    <div>
                        <span class="text-[10px] text-muted block">Project</span>
                        <span
                            class="font-bold text-ink block mt-0.5 text-xs truncate">{{ $lead->project?->name ?: 'Direct Inbound' }}</span>
                    </div>
                    @endif
                    @if($displayConfig['property_type'] ?? true)
                    <div>
                        <span class="text-[10px] text-muted block">Property Type</span>
                        <span
                            class="font-semibold text-ink block mt-0.5 text-xs">{{ $lead->actual_property_type ?: '—' }}</span>
                    </div>
                    @endif
                    @if(($displayConfig['budget'] ?? true) && ($lead->actual_budget || $lead->budget))
                    <div>
                        <span class="text-[10px] text-muted block">Budget / Unit Price</span>
                        <span class="font-bold text-primary block mt-0.5 text-xs">{{ \App\Support\LeadPropertyAndBudgetExtractor::formatBudget($lead->actual_budget ?: $lead->budget) }}</span>
                    </div>
                    @endif
                </div>

                @php
                    $metaSubmission = \App\Support\MetaSubmissionParser::parse($lead);
                @endphp

                @if(($displayConfig['meta_submission'] ?? true) && $metaSubmission['is_meta'] && ($metaSubmission['has_submission_data'] || !empty($metaSubmission['questions'])))
                    <div class="pt-3 border-t border-border space-y-2.5">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-bold text-muted uppercase tracking-wider flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-primary inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                Lead Form Responses
                            </span>
                            <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-surface text-ink border border-border">
                                {{ count($metaSubmission['questions']) }} {{ \Illuminate\Support\Str::plural('Response', count($metaSubmission['questions'])) }}
                            </span>
                        </div>

                        <!-- Dynamic Questions & Answers List (2-column compact grid) -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            @foreach($metaSubmission['questions'] as $qa)
                                <div class="p-2.5 bg-surface rounded-lg border border-border/80 flex flex-col justify-between">
                                    <span class="text-[10px] font-medium text-muted block mb-0.5">
                                        {{ $qa['question'] }}
                                    </span>
                                    <p class="text-xs font-semibold text-ink leading-snug break-words whitespace-pre-line">
                                        {{ $qa['answer'] }}
                                    </p>
                                </div>
                            @endforeach
                        </div>

                        <!-- Campaign Attribution Section -->
                        @if($displayConfig['meta_context'] ?? true)
                        <div class="p-2.5 bg-surface rounded-lg border border-border text-[11px] space-y-1.5">
                            <span class="font-bold text-ink block text-[10px] uppercase tracking-wider">
                                Campaign &amp; Source Attribution
                            </span>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-ink">
                                <div>
                                    <span class="text-[10px] text-muted block">Campaign</span>
                                    <span class="font-semibold block truncate text-xs mt-0.5" title="{{ $metaSubmission['context']['campaign'] }}">
                                        {{ $metaSubmission['context']['campaign'] }}
                                    </span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-muted block">Ad Set</span>
                                    <span class="font-semibold block truncate text-xs mt-0.5" title="{{ $metaSubmission['context']['adset'] }}">
                                        {{ $metaSubmission['context']['adset'] }}
                                    </span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-muted block">Ad (Creative)</span>
                                    <span class="font-semibold block truncate text-xs mt-0.5" title="{{ $metaSubmission['context']['ad'] }}">
                                        {{ $metaSubmission['context']['ad'] }}
                                    </span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-muted block">Lead Form</span>
                                    <span class="font-semibold block truncate text-xs mt-0.5" title="{{ $metaSubmission['context']['form'] }}">
                                        {{ $metaSubmission['context']['form'] }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>
                @endif

                @php
                    $isPureMetaRequirement = str_starts_with(trim($lead->requirement ?? ''), 'Meta Lead Submission:') || str_contains($lead->requirement ?? '', 'Meta Context:');
                @endphp
                @if(($displayConfig['requirement'] ?? true) && $lead->requirement && (! $metaSubmission['is_meta'] || ! $isPureMetaRequirement))
                    <div class="pt-2 border-t border-border">
                        <span class="text-[10px] font-bold text-muted uppercase block">Customer Requirement / Inquiry:</span>
                        <p class="text-xs text-ink mt-0.5 leading-relaxed bg-surface p-2.5 rounded-lg border border-border break-words whitespace-pre-line">
                            {{ \App\Support\ClientFacingText::label($lead->requirement) }}
                        </p>
                    </div>
                @endif
            </div>

            <!-- ========================================================= -->
            <!-- SECTION 3: LEAD SOURCE & META INTELLIGENCE               -->
            <!-- ========================================================= -->
            @canViewLeadSource
            <div class="p-4 bg-canvas rounded-xl border border-border space-y-2">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <span class="text-[10px] font-bold text-muted uppercase tracking-wider block">2 &bull; Lead Source
                        &amp; Ingestion</span>
                    <span class="text-[10px] font-mono text-muted">Received:
                        {{ $lead->created_at?->format('M d, Y H:i') }}</span>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    <div>
                        <span class="text-[10px] text-muted block">Source Channel</span>
                        <span
                            class="font-semibold text-ink block mt-0.5">{{ $lead->leadSource?->name ?: 'Direct / Portal' }}</span>
                    </div>
                    <div>
                        <span class="text-[10px] text-muted block">Campaign</span>
                        <span
                            class="font-semibold text-ink block mt-0.5 truncate">{{ $lead->campaign?->name ?: 'Standard' }}</span>
                    </div>
                    <div>
                        <span class="text-[10px] text-muted block">Lead Cost</span>
                        <span
                            class="font-mono font-semibold text-ink block mt-0.5">{{ number_format($lead->lead_cost, 2) }}
                            Cr</span>
                    </div>
                </div>

                @if($metaSubmission['is_meta'])
                    <div class="mt-2 p-2.5 bg-surface rounded-lg border border-border text-[11px] space-y-1">
                        <span class="font-bold text-ink block text-[10px] uppercase">Campaign Ingestion Context:</span>
                        <div class="grid grid-cols-2 gap-2 text-ink">
                            <div><span class="text-muted text-[10px]">Campaign:</span> <span
                                class="font-semibold truncate block">{{ $metaSubmission['context']['campaign'] }}</span></div>
                            <div><span class="text-muted text-[10px]">Ad Set:</span> <span
                                class="font-semibold truncate block">{{ $metaSubmission['context']['adset'] }}</span></div>
                            <div><span class="text-muted text-[10px]">Creative:</span> <span
                                class="font-semibold truncate block">{{ $metaSubmission['context']['ad'] }}</span></div>
                            <div><span class="text-muted text-[10px]">Form:</span> <span
                                class="font-semibold truncate block">{{ $metaSubmission['context']['form'] }}</span></div>
                        </div>
                    </div>
                @endif
            </div>
            @endcanViewLeadSource

            <!-- ========================================================= -->
            <!-- SECTION 4: ASSIGNMENT INFORMATION                         -->
            <!-- ========================================================= -->
            <div class="p-4 bg-canvas rounded-xl border border-border space-y-2">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <span class="text-[10px] font-bold text-muted uppercase tracking-wider block">3 &bull; Assignment
                        &amp; Ownership</span>
                    @if($lead->assignedTo)
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span
                                class="px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                Assigned
                            </span>
                            @if(!$lead->executive_first_response_at && !$lead->first_response_at)
                                <span
                                    class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200"
                                    title="Sales executive has not logged any response or stage progress yet">
                                    No Action Taken
                                </span>
                            @endif
                            @if(auth()->user()?->hasAnyRole(['Super Admin', 'super-admin']) || $isBuilder)
                                <button type="button"
                                    wire:click="unassignExecutive"
                                    wire:confirm="Remove executive assignment from this lead? The lead will become unassigned."
                                    class="px-2 py-0.5 rounded text-[10px] font-semibold bg-rose-50 text-rose-700 hover:bg-rose-100 border border-rose-200 transition cursor-pointer flex items-center gap-1">
                                    <svg class="w-3 h-3 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7a4 4 0 11-8 0 4 4 0 018 0zM9 14a6 6 0 00-6 6v1h12v-1a6 6 0 00-6-6zM21 12h-6" />
                                    </svg>
                                    <span>Unassign</span>
                                </button>
                                <button type="button"
                                    @click="$dispatch('open-lead-assign', { id: {{ $lead->id }} }); if (window.Livewire) Livewire.dispatch('open-lead-assign', { id: {{ $lead->id }} });"
                                    class="px-2 py-0.5 rounded text-[10px] font-semibold bg-surface text-ink hover:bg-canvas border border-border transition cursor-pointer flex items-center gap-1">
                                    <svg class="w-3 h-3 text-primary shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                                    </svg>
                                    <span>Reassign</span>
                                </button>
                            @endif
                        </div>
                    @else
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span
                                class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                Unassigned
                            </span>
                            @if(auth()->user()?->hasAnyRole(['Super Admin', 'super-admin']) || $isBuilder)
                                <button type="button"
                                    @click="$dispatch('open-lead-assign', { id: {{ $lead->id }} }); if (window.Livewire) Livewire.dispatch('open-lead-assign', { id: {{ $lead->id }} });"
                                    class="px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200 transition cursor-pointer flex items-center gap-1">
                                    <svg class="w-3 h-3 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                                    </svg>
                                    <span>Assign</span>
                                </button>
                            @endif
                        </div>
                    @endif
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    <div>
                        <span class="text-[10px] text-muted block">Assigned Executive</span>
                        <span
                            class="font-bold text-ink block mt-0.5">{{ $lead->assignedTo?->name ?: 'Unassigned' }}</span>
                    </div>
                    <div>
                        <span class="text-[10px] text-muted block">Executive Email</span>
                        <span
                            class="font-mono text-ink block mt-0.5 truncate">{{ $lead->assignedTo?->email ?: '—' }}</span>
                    </div>
                    <div>
                        <span class="text-[10px] text-muted block">Organization</span>
                        <span
                            class="font-semibold text-ink block mt-0.5">{{ $lead->client?->organization?->name ?: 'Default' }}</span>
                    </div>
                </div>

                @if(!empty($assignmentHistory) && count($assignmentHistory) > 0)
                    <div class="pt-2 border-t border-border">
                        <span class="text-[10px] font-bold text-muted uppercase block mb-1">Assignment Trail:</span>
                        <div class="space-y-1 max-h-24 overflow-y-auto text-[11px]">
                            @foreach($assignmentHistory as $asgn)
                                <div class="flex items-center justify-between p-1.5 rounded bg-surface border border-border">
                                    <span>Assigned to <strong
                                            class="text-ink">{{ $asgn->assignee?->name ?? 'User #' . $asgn->assigned_to }}</strong>
                                        by {{ $asgn->assigner?->name ?? 'System' }}</span>
                                    <span
                                        class="text-muted font-mono text-[10px]">{{ \Carbon\Carbon::parse($asgn->assigned_at)->format('M d, H:i') }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <!-- ========================================================= -->
            <!-- SECTION 5: CURRENT STAGE & SLA STATUS                     -->
            <!-- ========================================================= -->
            <div class="p-4 bg-canvas rounded-xl border border-border space-y-2">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <span class="text-[10px] font-bold text-muted uppercase tracking-wider block">4 &bull; Current Stage
                        &amp; SLA</span>
                    <span
                        class="text-xs font-bold text-primary">{{ ucfirst(str_replace('_', ' ', $lead->current_stage)) }}</span>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    <div>
                        <span class="text-[10px] text-muted block">First Response</span>
                        <span class="font-semibold text-ink block mt-0.5">
                            {{ $lead->executive_first_response_at?->format('M d, H:i') ?? 'Pending Action' }}
                        </span>
                    </div>
                    <div>
                        <span class="text-[10px] text-muted block">SLA Compliance</span>
                        <div class="mt-0.5">
                            <x-ui.badge status="{{ $lead->sla_badge }}">{{ $lead->sla_badge }}</x-ui.badge>
                            <p class="text-[10px] text-muted mt-1">Deadline: {{ $lead->executive_response_due_at?->format('d M H:i') ?? 'Not assigned' }}</p>
                            <p class="text-[10px] text-muted font-medium mt-0.5">Response Timing: {{ $lead->first_response_timing }}</p>
                        </div>
                    </div>
                    <div>
                        <span class="text-[10px] text-muted block">Deal Status</span>
                        <span class="font-bold text-ink block mt-0.5">{{ ucfirst($lead->status) }}</span>
                    </div>
                </div>
            </div>

            <!-- ========================================================= -->
            <!-- SECTION 5B: FOLLOW-UP & REMINDER OVERVIEW                 -->
            <!-- ========================================================= -->
            @php
                $nextFu = $lead->nextPendingFollowup();
                $lastFu = $lead->lastCompletedFollowup();
                $urgency = $lead->followup_urgency;
            @endphp
            <div class="p-4 bg-canvas rounded-xl border border-border space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="text-[10px] font-bold text-muted uppercase tracking-wider flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Follow-Up Schedule &amp; Activity
                    </span>
                    <div class="flex items-center gap-1.5">
                        @if($this->canManageFollowupAssignment())
                            <button type="button" wire:click="openAssignFollowupModal" class="px-2 py-1 rounded bg-surface border border-border text-[10px] font-bold text-ink hover:bg-canvas transition flex items-center gap-1 cursor-pointer">
                                <span>Assign Follow-Up</span>
                            </button>
                            <button type="button" wire:click="openRequestFollowupModal" class="px-2 py-1 rounded bg-primary/10 border border-primary/20 text-[10px] font-bold text-primary hover:bg-primary/20 transition flex items-center gap-1 cursor-pointer">
                                <span>Request Follow-Up</span>
                            </button>
                        @endif
                        @if($urgency === 'overdue')
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500 animate-pulse"></span>
                                Overdue Follow-up
                            </span>
                        @elseif($urgency === 'due_today')
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                Due Today
                            </span>
                        @elseif($urgency === 'upcoming')
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200">
                                Scheduled
                            </span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-neutral-100 text-neutral-600 border border-neutral-200">
                                No Pending Action
                            </span>
                        @endif
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                    <!-- Next Follow-up -->
                    <div class="p-2.5 rounded-lg bg-surface border border-border space-y-1">
                        <div class="flex items-center justify-between text-[10px] text-muted font-semibold">
                            <span>NEXT SCHEDULED</span>
                            @if($nextFu)
                                <span class="capitalize px-1.5 py-0.2 rounded bg-primary/10 text-primary font-bold">{{ $nextFu->type_label }}</span>
                            @endif
                        </div>
                        @if($nextFu)
                            <div class="font-bold text-ink text-sm">
                                {{ $nextFu->due_at->format('d M Y, h:i A') }}
                            </div>
                            <p class="text-[11px] text-muted truncate" title="{{ $nextFu->note }}">{{ $nextFu->note ?: 'Customer follow-up discussion' }}</p>

                            <!-- Assignment & Request Metadata Badges -->
                            <div class="flex flex-wrap items-center gap-1.5 pt-1 text-[10px]">
                                @if($nextFu->assignedTo)
                                    <span class="px-1.5 py-0.5 rounded bg-surface border border-border text-ink font-semibold flex items-center gap-1">
                                        <span>👤 {{ $nextFu->assignedTo->name }}</span>
                                        @if($nextFu->assignedBy)
                                            <span class="text-muted text-[9px]">(by {{ $nextFu->assignedBy->name }})</span>
                                        @endif
                                    </span>
                                @endif

                                @if($nextFu->isRequested())
                                    <span class="px-1.5 py-0.5 rounded font-bold border {{ $nextFu->request_status === 'completed' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : ($nextFu->request_status === 'in_progress' ? 'bg-indigo-50 text-indigo-700 border-indigo-200' : ($nextFu->request_status === 'accepted' ? 'bg-blue-50 text-blue-700 border-blue-200' : 'bg-amber-50 text-amber-700 border-amber-200')) }}">
                                        Req: {{ $nextFu->request_status_label }}
                                        @if($nextFu->requestedBy)
                                            <span class="font-normal opacity-80">(by {{ $nextFu->requestedBy->name }})</span>
                                        @endif
                                    </span>
                                @endif
                            </div>

                            @if($nextFu->escalation_level > 0)
                                <span class="inline-block mt-1 text-[10px] font-bold text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded border border-amber-200">
                                    ⚠️ Escalation Level {{ $nextFu->escalation_level }}: {{ $nextFu->escalation_notes }}
                                </span>
                            @endif

                            <!-- Action Row for Reassignment / Request Acceptance -->
                            <div class="flex flex-wrap items-center gap-2 pt-1.5 border-t border-border/60">
                                @if($this->canManageFollowupAssignment())
                                    <button type="button" wire:click="openReassignFollowupModal({{ $nextFu->id }})" class="text-[10px] font-semibold text-primary hover:underline cursor-pointer">
                                        Reassign Executive
                                    </button>
                                @endif

                                @if($nextFu->isRequested() && in_array($nextFu->request_status, ['pending', null]) && (auth()->id() === ($nextFu->assigned_to ?: $lead->assigned_to) || auth()->user()?->hasAnyRole(['Super Admin', 'super-admin'])))
                                    <div class="flex items-center gap-1">
                                        <button type="button" wire:click="acknowledgeFollowupRequest({{ $nextFu->id }}, 'accepted')" class="px-2 py-0.5 rounded bg-blue-600 hover:bg-blue-700 text-white font-bold text-[9px] cursor-pointer">
                                            Accept Request
                                        </button>
                                        <button type="button" wire:click="acknowledgeFollowupRequest({{ $nextFu->id }}, 'in_progress')" class="px-2 py-0.5 rounded bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-[9px] cursor-pointer">
                                            Start Working
                                        </button>
                                    </div>
                                @endif
                            </div>
                        @else
                            <div class="text-muted font-medium py-1">No upcoming follow-up scheduled</div>
                        @endif
                    </div>

                    <!-- Last Follow-up -->
                    <div class="p-2.5 rounded-lg bg-surface border border-border space-y-1">
                        <div class="flex items-center justify-between text-[10px] text-muted font-semibold">
                            <span>LAST COMPLETED</span>
                            @if($lastFu && $lastFu->outcome)
                                <span class="px-1.5 py-0.2 rounded bg-emerald-50 text-emerald-700 font-bold border border-emerald-200">{{ $lastFu->outcome_label }}</span>
                            @endif
                        </div>
                        @if($lastFu)
                            <div class="font-bold text-ink text-sm">
                                {{ ($lastFu->completed_at ?? $lastFu->due_at)->format('d M Y, h:i A') }}
                            </div>
                            <p class="text-[11px] text-muted truncate" title="{{ $lastFu->outcome_notes ?: $lastFu->note }}">
                                {{ $lastFu->outcome_notes ?: ($lastFu->note ?: 'Follow-up discussion completed') }}
                            </p>
                            @if($lastFu->completedBy)
                                <p class="text-[10px] text-muted font-mono">By {{ $lastFu->completedBy->name }}</p>
                            @endif
                            @if($lastFu->isRequested())
                                <div class="pt-1">
                                    <span class="inline-block text-[9px] font-bold text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200">
                                        Completed on request by {{ $lastFu->requestedBy?->name ?? 'Management' }}
                                    </span>
                                </div>
                            @endif
                        @else
                            <div class="text-muted font-medium py-1">No prior completed follow-ups</div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- ========================================================= -->
            <!-- SECTION 6: DYNAMIC STAGE-SPECIFIC DETAILS & ACTIONS       -->
            <!-- ========================================================= -->
            <div class="p-4 rounded-xl border-2 border-primary/20 bg-primary/5 space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-xs font-bold text-ink uppercase tracking-wider flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-primary"></span>
                            <span>Stage Actions: {{ ucfirst(str_replace('_', ' ', $lead->current_stage)) }}</span>
                        </h3>
                        <p class="text-[11px] text-muted mt-0.5">Available sales operations for the current pipeline
                            state</p>
                    </div>
                    <x-ui.badge
                        status="{{ $lead->current_stage }}">{{ ucfirst(str_replace('_', ' ', $lead->current_stage)) }}</x-ui.badge>
                </div>

                @if($isBuilder)
                    <div class="text-xs space-y-2">
                        @if($normalizedStage === 'follow_up')@foreach($leadFollowups as $followup)
                        <p>{{ $followup->due_at }} · {{ $followup->note }} · {{ $followup->status }}</p>@endforeach @endif
                        @if($normalizedStage === 'meeting_scheduled')
                            <p>{{ $latestMeeting?->scheduled_at }} · {{ $latestMeeting?->location }} ·
                                {{ $latestMeeting?->outcome }}</p>
                        <p>{{ $latestMeeting?->notes }}</p>@endif
                        @if($normalizedStage === 'site_visit')
                            <p>{{ $latestSiteVisit?->visit_date }} · {{ $latestSiteVisit?->attendance }} ·
                                {{ $latestSiteVisit?->outcome }}</p>
                        <p>{{ $latestSiteVisit?->remarks }}</p>@endif
                        @if($normalizedStage === 'negotiation')
                        <p>{{ $latestNegotiation?->notes }}</p>@endif
                        @if(in_array($normalizedStage, ['booking', 'payment_received', 'closed_won']))
                            <p>Unit: {{ $latestBooking?->unit?->unit_number }} · Booking: {{ $latestBooking?->booking_amount }}
                                · {{ $latestBooking?->booked_at }}</p>@foreach($latestBooking?->payments ?? [] as $payment)
                                    <p>Payment: {{ $payment->amount }} · {{ $payment->paid_at }} · {{ $payment->transaction_id }}</p>
                        @endforeach @endif
                        @if(!in_array($normalizedStage, ['closed_won', 'lost', 'invalid', 'unreachable', 'replaced', 'pending_credit']))<x-ui.button
                            x-on:click="$dispatch('open-lead-assign', { id: {{ $lead->id }} }); if (window.Livewire) Livewire.dispatch('open-lead-assign', { id: {{ $lead->id }} });"
                        variant="secondary">{{ $lead->assigned_to ? 'Reassign Lead' : 'Assign Lead' }}</x-ui.button>@endif
                    </div>
                @else
                    @php
                        $nextForms = ['follow_up' => 'meeting_scheduled', 'meeting_scheduled' => 'site_visit', 'site_visit' => 'negotiation', 'negotiation' => 'booking', 'booking' => 'payment_received'];
                        $actionStage = \App\Models\Lead::usesSalesPanel() ? $normalizedStage : $lead->current_stage;
                        if ($isSalesExecutive && ($nextForms[$normalizedStage] ?? null) === $targetNextStage)
                            $actionStage = $targetNextStage;
                    @endphp
                    <!-- ----------------------------------------------------- -->
                    <!-- STAGE: NEW / CONTACTED                                -->
                    <!-- ----------------------------------------------------- -->
                    @if(in_array($actionStage, ['new', 'contacted', 'connected', 'qualified', 'assigned']))
                        <div class="space-y-3 bg-surface p-3.5 rounded-lg border border-border">
                            <span class="text-xs font-bold text-ink block">Log Customer Interaction &amp; Update Status</span>
                            <a href="tel:{{ $lead->mobile }}" class="text-xs text-primary underline">Contact Customer</a>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="text-[10px] font-semibold text-muted">Call Outcome</label>
                                    <x-ui.themed-select class="w-full" searchable  wire:model="callOutcome"
                                        trigger-class="w-full text-xs p-2 rounded-lg border border-border bg-canvas text-ink mt-0.5">
                                        <option value="Connected">Connected &amp; Discussed</option>
                                        <option value="Interested">Interested in Project</option>
                                        <option value="Call Back Later">Call Back Later / Busy</option>
                                        <option value="Ringing No Response">Ringing No Response</option>
                                        <option value="Switch Off / Unreachable">Switch Off / Unreachable</option>
                                        <option value="Wrong Number">Wrong / Invalid Number</option>
                                    </x-ui.themed-select>
                                </div>
                                <div>
                                    <label class="text-[10px] font-semibold text-muted">Duration (Minutes)</label>
                                    <input type="number" wire:model="callDuration"
                                        class="w-full text-xs p-2 rounded-lg border border-border bg-canvas text-ink mt-0.5"
                                        min="0">
                                </div>
                            </div>
                            <div class="flex items-center gap-2 pt-1">
                                <x-ui.button wire:click="logCallAction" variant="primary" class="text-xs flex-1">
                                    Log Call &amp; Update First Response
                                </x-ui.button>
                                <x-ui.button wire:click="markInterestedAction" variant="secondary" class="text-xs">
                                    Mark Interested &rarr;
                                </x-ui.button>
                            </div>
                        </div>

                        <!-- ----------------------------------------------------- -->
                        <!-- STAGE: INTERESTED                                     -->
                        <!-- ----------------------------------------------------- -->
                    @elseif($actionStage === 'interested')
                        <div class="space-y-3 bg-surface p-3.5 rounded-lg border border-border">
                            <span class="text-xs font-bold text-ink block">Customer Interest &amp; Next Action</span>
                            <div>
                                <label class="text-[10px] font-semibold text-muted">Customer Notes / Specific
                                    Requirements</label>
                                <textarea wire:model="actionRemarks" rows="2"
                                    placeholder="e.g. Looking for 3 BHK high-floor facing park, ready to visit next weekend..."
                                    class="w-full text-xs p-2 rounded-lg border border-border bg-canvas text-ink mt-0.5"></textarea>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div>
                                    <label class="text-[10px] font-semibold text-muted">Follow-up Type</label>
                                    <x-ui.themed-select wire:model="followupType" placeholder="Select Type" class="w-full mt-0.5">
                                        <option value="call">Phone Call</option>
                                        <option value="whatsapp">WhatsApp Message</option>
                                        <option value="meeting">Face-to-Face Meeting</option>
                                        <option value="site_visit">Site Visit</option>
                                        <option value="other">Other Action</option>
                                    </x-ui.themed-select>
                                </div>
                                <div>
                                    <label class="text-[10px] font-semibold text-muted">Schedule Date &amp; Time</label>
                                    <input type="datetime-local" wire:model="followupDueAt"
                                        class="w-full text-xs p-2 rounded-lg border border-border bg-canvas text-ink mt-0.5">
                                </div>
                                <div>
                                    <label class="text-[10px] font-semibold text-muted">Objective / Agenda</label>
                                    <input type="text" wire:model="followupNote" placeholder="Confirm site visit appointment"
                                        class="w-full text-xs p-2 rounded-lg border border-border bg-canvas text-ink mt-0.5">
                                </div>
                            </div>
                            <div class="flex items-center gap-2 pt-1">
                                <x-ui.button wire:click="scheduleFollowupAction" variant="primary" class="text-xs flex-1">
                                    Schedule Follow-up &amp; Move Stage
                                </x-ui.button>
                            </div>
                        </div>

                        <!-- ----------------------------------------------------- -->
                        <!-- STAGE: FOLLOW-UP & REMINDER                           -->
                        <!-- ----------------------------------------------------- -->
                    @elseif($actionStage === 'follow_up')
                        <div class="space-y-4 bg-surface p-3.5 rounded-lg border border-border">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-ink block">Follow-Up Management</span>
                                <span class="text-[10px] text-muted font-medium">Stage 3 &bull; Scheduled Actions</span>
                            </div>
                            @php
                                $activeFollowup = $lead->followups()->where(function($q) { $q->whereNull('status')->orWhere('status', 'pending'); })->orderBy('due_at', 'asc')->first();
                            @endphp
                            @if($activeFollowup)
                                <div class="p-3 rounded-lg bg-amber-50/70 border border-amber-200 text-xs space-y-2">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="space-y-0.5">
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <span class="font-bold text-amber-950">{{ $activeFollowup->type_label }}</span>
                                                <span class="text-[10px] font-bold px-1.5 py-0.2 rounded bg-amber-200/80 text-amber-800">Pending</span>
                                                @if($activeFollowup->urgency === 'overdue')
                                                    <span class="text-[10px] font-bold px-1.5 py-0.2 rounded bg-rose-100 text-rose-700 border border-rose-300">Overdue</span>
                                                @elseif($activeFollowup->urgency === 'due_today')
                                                    <span class="text-[10px] font-bold px-1.5 py-0.2 rounded bg-amber-100 text-amber-800 border border-amber-300">Due Today</span>
                                                @endif
                                            </div>
                                            <p class="text-amber-900 font-medium">{{ $activeFollowup->note ?: 'Follow-up discussion scheduled' }}</p>
                                        </div>
                                        <span class="font-mono text-xs font-bold text-amber-900 bg-surface px-2.5 py-1 rounded border border-amber-200 shrink-0">
                                            {{ \Carbon\Carbon::parse($activeFollowup->due_at)->format('M d, H:i') }}
                                        </span>
                                    </div>

                                    @if($activeFollowup->escalation_level > 0)
                                        <div class="p-2 rounded bg-rose-100/80 border border-rose-300 text-[11px] text-rose-900 flex items-center gap-2">
                                            <svg class="w-4 h-4 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                            <span><strong>Escalated (Tier {{ $activeFollowup->escalation_level }}):</strong> {{ $activeFollowup->escalation_notes }}</span>
                                        </div>
                                    @endif

                                    <!-- Action Buttons -->
                                    <div class="flex items-center gap-2 pt-1 flex-wrap">
                                        <button type="button" wire:click="$set('completingFollowupId', {{ $activeFollowup->id }})"
                                            class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs flex-1 cursor-pointer transition">
                                            Complete Follow-Up
                                        </button>
                                        <button type="button" wire:click="$set('reschedulingFollowupId', {{ $activeFollowup->id }})"
                                            class="px-3 py-1.5 rounded-lg bg-surface border border-border text-ink hover:bg-canvas font-semibold text-xs cursor-pointer transition">
                                            Reschedule
                                        </button>
                                        <button type="button" wire:click="$set('cancellingFollowupId', {{ $activeFollowup->id }})"
                                            class="px-3 py-1.5 rounded-lg bg-rose-50 border border-rose-200 text-rose-700 hover:bg-rose-100 font-semibold text-xs cursor-pointer transition">
                                            Cancel
                                        </button>
                                    </div>
                                </div>

                                <!-- Inline Complete Form -->
                                @if($completingFollowupId === $activeFollowup->id)
                                    <div class="p-3.5 rounded-lg border border-emerald-200 bg-emerald-50/40 space-y-3">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-bold text-emerald-950">Record Follow-Up Outcome</span>
                                            <button type="button" wire:click="$set('completingFollowupId', null)" class="text-xs text-muted hover:text-ink cursor-pointer">&times; Close</button>
                                        </div>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                                            <div>
                                                <label class="text-[10px] font-semibold text-ink">Outcome Status <span class="text-rose-500">*</span></label>
                                                <x-ui.themed-select wire:model="followupOutcome" placeholder="Select Outcome" class="w-full mt-0.5">
                                                    @foreach(\App\Models\Followup::outcomeOptions() as $val => $lbl)
                                                        <option value="{{ $val }}">{{ $lbl }}</option>
                                                    @endforeach
                                                </x-ui.themed-select>
                                            </div>
                                            <div>
                                                <label class="text-[10px] font-semibold text-ink">Discussion Notes / Remarks</label>
                                                <input type="text" wire:model="followupOutcomeNotes" placeholder="Client feedback and discussion summary"
                                                    class="w-full text-xs p-2 rounded-lg border border-border bg-surface text-ink mt-0.5">
                                            </div>
                                        </div>

                                        <!-- Schedule Next Step Checkbox -->
                                        <div class="pt-2 border-t border-emerald-200/60 space-y-2">
                                            <label class="flex items-center gap-2 cursor-pointer text-xs font-semibold text-ink">
                                                <input type="checkbox" wire:model.live="scheduleNextFollowup" class="rounded border-border text-primary focus:ring-primary">
                                                <span>Schedule Next Follow-Up Step</span>
                                            </label>

                                            @if($scheduleNextFollowup)
                                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 pt-1 text-xs">
                                                    <div>
                                                        <label class="text-[10px] font-semibold text-muted">Next Type</label>
                                                        <x-ui.themed-select wire:model="nextFollowupType" placeholder="Select Type" class="w-full mt-0.5">
                                                            <option value="call">Phone Call</option>
                                                            <option value="whatsapp">WhatsApp</option>
                                                            <option value="meeting">Meeting</option>
                                                            <option value="site_visit">Site Visit</option>
                                                            <option value="other">Other</option>
                                                        </x-ui.themed-select>
                                                    </div>
                                                    <div>
                                                        <label class="text-[10px] font-semibold text-muted">Next Date &amp; Time</label>
                                                        <input type="datetime-local" wire:model="nextFollowupDueAt"
                                                            class="w-full text-xs p-2 rounded-lg border border-border bg-surface text-ink mt-0.5">
                                                    </div>
                                                    <div>
                                                        <label class="text-[10px] font-semibold text-muted">Next Objective</label>
                                                        <input type="text" wire:model="nextFollowupNote" placeholder="Confirm pricing / token"
                                                            class="w-full text-xs p-2 rounded-lg border border-border bg-surface text-ink mt-0.5">
                                                    </div>
                                                </div>
                                            @endif
                                        </div>

                                        <div class="flex items-center gap-2 pt-1">
                                            <button type="button" wire:click="completeFollowupAction({{ $activeFollowup->id }})"
                                                class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-lg transition flex-1 cursor-pointer">
                                                Save &amp; Complete Follow-up
                                            </button>
                                        </div>
                                    </div>
                                @endif

                                <!-- Inline Reschedule Form -->
                                @if($reschedulingFollowupId === $activeFollowup->id)
                                    <div class="p-3.5 rounded-lg border border-blue-200 bg-blue-50/40 space-y-3">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-bold text-blue-950">Reschedule Follow-Up</span>
                                            <button type="button" wire:click="$set('reschedulingFollowupId', null)" class="text-xs text-muted hover:text-ink cursor-pointer">&times; Close</button>
                                        </div>
                                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 text-xs">
                                            <div>
                                                <label class="text-[10px] font-semibold text-ink">Type</label>
                                                <x-ui.themed-select wire:model="followupType" placeholder="Select Type" class="w-full mt-0.5">
                                                    <option value="call">Phone Call</option>
                                                    <option value="whatsapp">WhatsApp</option>
                                                    <option value="meeting">Meeting</option>
                                                    <option value="site_visit">Site Visit</option>
                                                    <option value="other">Other</option>
                                                </x-ui.themed-select>
                                            </div>
                                            <div>
                                                <label class="text-[10px] font-semibold text-ink">New Date &amp; Time <span class="text-rose-500">*</span></label>
                                                <input type="datetime-local" wire:model="followupDueAt"
                                                    class="w-full text-xs p-2 rounded-lg border border-border bg-surface text-ink mt-0.5">
                                            </div>
                                            <div>
                                                <label class="text-[10px] font-semibold text-ink">Updated Agenda / Remarks</label>
                                                <input type="text" wire:model="followupNote" placeholder="Client requested evening call"
                                                    class="w-full text-xs p-2 rounded-lg border border-border bg-surface text-ink mt-0.5">
                                            </div>
                                        </div>
                                        <button type="button" wire:click="rescheduleFollowupAction({{ $activeFollowup->id }})"
                                            class="w-full py-2 bg-primary hover:bg-primary-dark text-white font-bold text-xs rounded-lg transition cursor-pointer">
                                            Save Rescheduled Follow-up
                                        </button>
                                    </div>
                                @endif

                                <!-- Inline Cancel Form -->
                                @if($cancellingFollowupId === $activeFollowup->id)
                                    <div class="p-3.5 rounded-lg border border-rose-200 bg-rose-50/40 space-y-3">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-bold text-rose-950">Cancel Scheduled Follow-Up</span>
                                            <button type="button" wire:click="$set('cancellingFollowupId', null)" class="text-xs text-muted hover:text-ink cursor-pointer">&times; Close</button>
                                        </div>
                                        <div>
                                            <label class="text-[10px] font-semibold text-rose-900">Cancellation Reason</label>
                                            <textarea wire:model="cancellationReason" rows="2" placeholder="e.g. Customer requested not to call, or advance to site visit directly"
                                                class="w-full text-xs p-2 rounded-lg border border-rose-200 bg-surface text-ink mt-0.5"></textarea>
                                        </div>
                                        <button type="button" wire:click="cancelFollowupAction({{ $activeFollowup->id }})"
                                            class="w-full py-2 bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs rounded-lg transition cursor-pointer">
                                            Confirm Cancellation
                                        </button>
                                    </div>
                                @endif
                            @else
                                <p class="text-xs text-muted">No pending follow-up scheduled. Schedule a new one below:</p>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <div>
                                        <label class="text-[10px] font-semibold text-muted">Type</label>
                                        <x-ui.themed-select wire:model="followupType" placeholder="Select Type" class="w-full mt-0.5">
                                            <option value="call">Phone Call</option>
                                            <option value="whatsapp">WhatsApp Message</option>
                                            <option value="meeting">Face-to-Face Meeting</option>
                                            <option value="site_visit">Site Visit</option>
                                            <option value="other">Other Action</option>
                                        </x-ui.themed-select>
                                    </div>
                                    <div>
                                        <label class="text-[10px] font-semibold text-muted">Date &amp; Time</label>
                                        <input type="datetime-local" wire:model="followupDueAt"
                                            class="w-full text-xs p-2 rounded-lg border border-border bg-canvas text-ink mt-0.5">
                                    </div>
                                    <div>
                                        <label class="text-[10px] font-semibold text-muted">Agenda / Objective</label>
                                        <input type="text" wire:model="followupNote" placeholder="Follow-up call objective"
                                            class="w-full text-xs p-2 rounded-lg border border-border bg-canvas text-ink mt-0.5">
                                    </div>
                                </div>
                                <x-ui.button wire:click="scheduleFollowupAction" variant="primary" class="w-full text-xs">
                                    Schedule Follow-up
                                </x-ui.button>
                            @endif

                            <!-- Follow-up History Trail -->
                            @if(!empty($leadFollowups) && $leadFollowups->count() > 0)
                                <div class="pt-3 border-t border-border space-y-2">
                                    <span class="text-[10px] font-bold text-muted uppercase tracking-wider block">Follow-up History ({{ $leadFollowups->count() }})</span>
                                    <div class="space-y-1.5 max-h-48 overflow-y-auto">
                                        @foreach($leadFollowups as $fu)
                                            <div class="p-2 rounded-lg bg-surface border border-border text-xs flex items-start justify-between gap-2">
                                                <div class="min-w-0 flex-1 space-y-0.5">
                                                    <div class="flex items-center gap-1.5 flex-wrap">
                                                        <span class="font-bold text-ink">{{ $fu->type_label }}</span>
                                                        @if($fu->status === 'completed')
                                                            <span class="px-1.5 py-0.2 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                                {{ $fu->outcome_label ?: 'Completed' }}
                                                            </span>
                                                        @elseif($fu->status === 'cancelled')
                                                            <span class="px-1.5 py-0.2 rounded text-[10px] font-bold bg-neutral-100 text-neutral-600 border border-neutral-200">Cancelled</span>
                                                        @else
                                                            <span class="px-1.5 py-0.2 rounded text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">Pending</span>
                                                        @endif
                                                    </div>
                                                    <p class="text-[11px] text-muted truncate">
                                                        {{ $fu->outcome_notes ?: ($fu->note ?: 'No notes recorded') }}
                                                    </p>
                                                    @if($fu->status === 'cancelled' && $fu->cancellation_reason)
                                                        <p class="text-[10px] text-rose-700 italic">Reason: {{ $fu->cancellation_reason }}</p>
                                                    @endif
                                                </div>
                                                <div class="text-right shrink-0">
                                                    <span class="text-[10px] font-mono text-muted block">{{ \Carbon\Carbon::parse($fu->due_at)->format('d M, H:i') }}</span>
                                                    <span class="text-[9px] text-muted block">By {{ $fu->creator?->name ?? 'User' }}</span>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>

                        <!-- ----------------------------------------------------- -->
                        <!-- STAGE: MEETING SCHEDULED                              -->
                        <!-- ----------------------------------------------------- -->
                    @elseif(in_array($actionStage, ['meeting', 'meeting_scheduled']))
                        <div class="space-y-3 bg-surface p-3.5 rounded-lg border border-border">
                            <span class="text-xs font-bold text-ink block">Meeting Schedule &amp; Execution</span>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="text-[10px] font-semibold text-muted">Date &amp; Time</label>
                                    <input type="datetime-local" wire:model="meetingScheduledAt"
                                        class="w-full text-xs p-2 rounded-lg border border-border bg-canvas text-ink mt-0.5">
                                </div>
                                <div>
                                    <label class="text-[10px] font-semibold text-muted">Location</label>
                                    <input type="text" wire:model="meetingLocation" placeholder="Site Office / Client Office"
                                        class="w-full text-xs p-2 rounded-lg border border-border bg-canvas text-ink mt-0.5">
                                </div>
                            </div>
                            <div>
                                <label class="text-[10px] font-semibold text-muted">Meeting Notes / Agenda</label>
                                <textarea wire:model="meetingNotes" rows="2"
                                    placeholder="Discussion on floor plan, payment plans..."
                                    class="w-full text-xs p-2 rounded-lg border border-border bg-canvas text-ink mt-0.5"></textarea>
                            </div>
                            <div class="flex items-center gap-2 pt-1">
                                <x-ui.button wire:click="scheduleMeetingAction" variant="primary" class="text-xs flex-1">
                                    Save / Update Meeting
                                </x-ui.button>
                                <button type="button" wire:click="updateMeetingStatusAction('Completed')"
                                    class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs cursor-pointer">
                                    Meeting Completed
                                </button>
                            </div>
                        </div>

                        <!-- ----------------------------------------------------- -->
                        <!-- STAGE: SITE VISIT COMPLETED                           -->
                        <!-- ----------------------------------------------------- -->
                    @elseif($actionStage === 'site_visit')
                        <div class="space-y-3 bg-surface p-3.5 rounded-lg border border-border">
                            <span class="text-xs font-bold text-ink block">Site Visit Execution &amp; Feedback</span>
                            <textarea wire:model="visitRemarks" placeholder="Visit remarks"
                                class="w-full text-xs rounded-lg border-border"></textarea>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="text-[10px] font-semibold text-muted">Visit Date &amp; Time</label>
                                    <input type="datetime-local" wire:model="visitDate"
                                        class="w-full text-xs p-2 rounded-lg border border-border bg-canvas text-ink mt-0.5">
                                </div>
                                <div>
                                    <label class="text-[10px] font-semibold text-muted">Attendance Status</label>
                                    <x-ui.themed-select class="w-full" searchable  wire:model="visitAttendance"
                                        trigger-class="w-full text-xs p-2 rounded-lg border border-border bg-canvas text-ink mt-0.5">
                                        <option value="attended">Attended Visit</option>
                                        @if(!\App\Models\Lead::usesSalesPanel())
                                        <option value="scheduled">Scheduled / Upcoming</option>@endif
                                        <option value="no_show">No Show / Cancelled</option>
                                    </x-ui.themed-select>
                                </div>
                            </div>
                            <div>
                                <label class="text-[10px] font-semibold text-muted">Customer Visit Feedback &amp; Unit
                                    Preference</label>
                                <input type="text" wire:model="visitOutcome"
                                    placeholder="e.g. Liked 3 BHK corner unit, requested pricing sheet"
                                    class="w-full text-xs p-2 rounded-lg border border-border bg-canvas text-ink mt-0.5">
                            </div>
                            <div class="flex items-center gap-2 pt-1">
                                <x-ui.button wire:click="saveSiteVisitAction" variant="primary" class="text-xs flex-1">
                                    Save Visit Details / Remarks
                                </x-ui.button>
                            </div>
                        </div>

                        <!-- ----------------------------------------------------- -->
                        <!-- STAGE: NEGOTIATION & PROPOSAL                         -->
                        <!-- ----------------------------------------------------- -->
                    @elseif($actionStage === 'negotiation' && \App\Support\FinancialVisibility::allowed())
                        <div class="space-y-3 bg-surface p-3.5 rounded-lg border border-border">
                            <span class="text-xs font-bold text-ink block">Negotiation &amp; Commercial Terms</span>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="text-[10px] font-semibold text-muted">Quoted / Agreed Amount (₹)</label>
                                    <input type="text" wire:model="quotedAmount" placeholder="e.g. 8500000"
                                        class="w-full text-xs p-2 rounded-lg border border-border bg-canvas text-ink mt-0.5">
                                </div>
                                <div>
                                    <label class="text-[10px] font-semibold text-muted">Payment Plan Option</label>
                                    <x-ui.themed-select class="w-full" searchable
                                        trigger-class="w-full text-xs p-2 rounded-lg border border-border bg-canvas text-ink mt-0.5">
                                        <option>Construction Linked Plan (CLP)</option>
                                        <option>Down Payment Plan (DP)</option>
                                        <option>Subvention / Flexi Plan</option>
                                    </x-ui.themed-select>
                                </div>
                            </div>
                            <div>
                                <label class="text-[10px] font-semibold text-muted">Negotiation Notes &amp; Special
                                    Concessions</label>
                                <textarea wire:model="negotiationNotes" rows="2"
                                    placeholder="e.g. Offered free car parking and modular kitchen discount on booking within 3 days..."
                                    class="w-full text-xs p-2 rounded-lg border border-border bg-canvas text-ink mt-0.5"></textarea>
                            </div>
                            <div class="flex items-center gap-2 pt-1">
                                <x-ui.button wire:click="saveNegotiationAction" variant="primary" class="text-xs flex-1">
                                    Save Negotiation Notes
                                </x-ui.button>
                            </div>
                        </div>

                        <!-- ----------------------------------------------------- -->
                        <!-- STAGE: UNIT BOOKING                                   -->
                        <!-- ----------------------------------------------------- -->
                    @elseif($actionStage === 'booking' && \App\Support\FinancialVisibility::allowed())
                        <div class="space-y-3 bg-surface p-3.5 rounded-lg border border-border">
                            <span class="text-xs font-bold text-ink block">Unit Booking Details</span>
                            @if($latestBooking)
                                <p class="text-xs">Selected unit: {{ $latestBooking->unit?->unit_number }} · Booked:
                            {{ $latestBooking->booked_at }}</p>@endif
                            <div class="grid grid-cols-3 gap-3">
                                <div>
                                    <label class="text-[10px] font-semibold text-muted">Unit / Flat No.</label>
                                    <input type="text" wire:model="bookingUnit" placeholder="e.g. Tower B - 1204"
                                        class="w-full text-xs p-2 rounded-lg border border-border bg-canvas text-ink mt-0.5">
                                </div>
                                <div>
                                    <label class="text-[10px] font-semibold text-muted">Token / Booking Amount (₹)</label>
                                    <input type="number" wire:model="bookingAmount" placeholder="e.g. 200000"
                                        class="w-full text-xs p-2 rounded-lg border border-border bg-canvas text-ink mt-0.5">
                                </div>
                                <div>
                                    <label class="text-[10px] font-semibold text-muted">Booking Date</label>
                                    <input type="date" wire:model="bookingDate"
                                        class="w-full text-xs p-2 rounded-lg border border-border bg-canvas text-ink mt-0.5">
                                </div>
                            </div>
                            <div class="flex items-center gap-2 pt-1">
                                <x-ui.button wire:click="saveBookingAction" variant="primary" class="text-xs flex-1">
                                    Record Booking Details &rarr;
                                </x-ui.button>
                            </div>
                        </div>

                        <!-- ----------------------------------------------------- -->
                        <!-- STAGE: PAYMENT RECEIVED                               -->
                        <!-- ----------------------------------------------------- -->
                    @elseif($actionStage === 'payment_received' && \App\Support\FinancialVisibility::allowed())
                        <div class="space-y-3 bg-surface p-3.5 rounded-lg border border-border">
                            <span class="text-xs font-bold text-ink block">Record Payment &amp; Verify Receipt</span>
                            <div class="grid grid-cols-3 gap-3">
                                <div>
                                    <label class="text-[10px] font-semibold text-muted">Payment Amount (₹)</label>
                                    <input type="number" wire:model="paymentAmount"
                                        class="w-full text-xs p-2 rounded-lg border border-border bg-canvas text-ink mt-0.5">
                                </div>
                                <div>
                                    <label class="text-[10px] font-semibold text-muted">Payment Method</label>
                                    <x-ui.themed-select class="w-full" searchable  wire:model="paymentMethod"
                                        trigger-class="w-full text-xs p-2 rounded-lg border border-border bg-canvas text-ink mt-0.5">
                                        <option value="Bank Transfer">Bank Transfer / NEFT / RTGS</option>
                                        <option value="UPI">UPI / Net Banking</option>
                                        <option value="Cheque">Cheque</option>
                                        <option value="Cash">Cash / Other</option>
                                    </x-ui.themed-select>
                                </div>
                                <div>
                                    <label class="text-[10px] font-semibold text-muted">Transaction ID / Cheque No.</label>
                                    <input type="text" wire:model="paymentTransactionId" placeholder="e.g. TXN9872134"
                                        class="w-full text-xs p-2 rounded-lg border border-border bg-canvas text-ink mt-0.5">
                                </div>
                            </div>
                            <div class="flex items-center gap-2 pt-1">
                                <x-ui.button wire:click="recordPaymentAction" variant="primary" class="text-xs flex-1">
                                    Record Payment &amp; Update Ledger
                                </x-ui.button>
                                <button type="button" wire:click="markClosedWonAction"
                                    class="px-4 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs cursor-pointer shadow-2xs">
                                    Mark Closed Won &rarr;
                                </button>
                            </div>
                        </div>

                        <!-- ----------------------------------------------------- -->
                        <!-- STAGE: CLOSED WON (Read-only Summary)                 -->
                        <!-- ----------------------------------------------------- -->
                    @elseif($actionStage === 'closed_won')
                        <div class="p-3.5 rounded-lg bg-emerald-50 border border-emerald-200 text-xs space-y-2">
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
                                <span class="font-bold text-emerald-900 text-sm">Deal Closed Won</span>
                            </div>
                            <p class="text-[11px] text-emerald-800">
                                This lead has completed the sales lifecycle and is fully closed won. Booking and payment records
                                are saved in the audit ledger.
                            </p>
                            @if($latestBooking)
                                <div
                                    class="p-2 bg-surface rounded border border-emerald-200 grid grid-cols-2 gap-2 text-[11px] text-ink">
                                    <div><span class="text-muted block text-[10px]">Booking Amount:</span>
                                        @if(\App\Support\FinancialVisibility::allowed())<strong>₹{{ number_format($latestBooking->booking_amount) }}</strong>@endif</div>
                                    <div><span class="text-muted block text-[10px]">Booked Date:</span>
                                        <strong>{{ $latestBooking->booked_at?->format('d M Y') }}</strong></div>
                                </div>
                            @endif
                        </div>

                        <!-- ----------------------------------------------------- -->
                        <!-- STAGE: LOST / INVALID (Read-only Summary)             -->
                        <!-- ----------------------------------------------------- -->
                    @elseif(in_array($actionStage, ['lost', 'closed_lost', 'invalid', 'unreachable']))
                        <div class="p-3.5 rounded-lg bg-neutral-100 border border-neutral-300 text-xs space-y-2">
                            <span class="font-bold text-neutral-800 block text-sm">Outcome:
                                {{ ucfirst(str_replace('_', ' ', $actionStage)) }}</span>
                            <p class="text-[11px] text-neutral-700">
                                This lead has been marked as {{ $actionStage }}. See activity timeline for the recorded reason
                                and detailed remarks.
                            </p>
                        </div>
                    @endif

                    @if($isSalesExecutive && in_array('addRemarksAction', $salesActions))
                        @if($normalizedStage !== 'interested')<textarea aria-label="Customer interaction notes"
                            wire:model="actionRemarks" rows="2" placeholder="Customer interaction notes"
                        class="w-full text-xs rounded-lg border-border"></textarea>@endif
                        <x-ui.button wire:click="addRemarksAction" variant="secondary" class="text-xs">Add Remarks /
                            Notes</x-ui.button>
                    @endif
                    <!-- General Stage Progression Buttons & Outcome Handlers -->
                    @if(!in_array($actionStage, ['closed_won', 'lost', 'closed_lost', 'invalid', 'unreachable', 'replaced', 'pending_credit']))
                        <div class="pt-3 border-t border-primary/20 flex items-center justify-between gap-2 flex-wrap text-xs">
                            @if($isSalesExecutive)
                                <div class="flex gap-2 items-center">
                                    @if(isset($nextForms[$normalizedStage]))<button type="button"
                                        wire:click="$set('targetNextStage','{{ $nextForms[$normalizedStage] }}')"
                                    class="px-2.5 py-1 rounded bg-surface border border-border font-semibold text-xs">{{ ucwords(str_replace('_', ' ', $nextForms[$normalizedStage])) }}</button>@endif
                                    @if($targetNextStage && !in_array($targetNextStage, ['lost_form', 'invalid_form']))<button
                                        type="button" wire:click="$set('targetNextStage','')" class="text-xs text-muted">Back to
                                    current stage</button>@endif
                                </div>
                            @else
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] font-bold text-muted uppercase">Advance To:</span>
                                    @if(in_array($actionStage, ['new', 'contacted', 'connected']))
                                        <button type="button" wire:click="markInterestedAction"
                                            class="px-2.5 py-1 rounded bg-surface border border-border hover:bg-canvas font-semibold text-[11px] cursor-pointer">Interested</button>
                                    @endif
                                    @if(in_array($actionStage, ['interested', 'follow_up']))
                                        <button type="button" wire:click="$set('targetNextStage', 'meeting_scheduled')"
                                            class="px-2.5 py-1 rounded bg-surface border border-border hover:bg-canvas font-semibold text-[11px] cursor-pointer">Meeting</button>
                                        <button type="button" wire:click="$set('targetNextStage', 'site_visit')"
                                            class="px-2.5 py-1 rounded bg-surface border border-border hover:bg-canvas font-semibold text-[11px] cursor-pointer">Site
                                            Visit</button>
                                    @endif
                                    @if(in_array($actionStage, ['meeting', 'meeting_scheduled', 'site_visit']))
                                        <button type="button" wire:click="$set('targetNextStage', 'negotiation')"
                                            class="px-2.5 py-1 rounded bg-surface border border-border hover:bg-canvas font-semibold text-[11px] cursor-pointer">Negotiation</button>
                                    @endif
                                    @if($actionStage === 'negotiation')
                                        <button type="button" wire:click="$set('targetNextStage', 'booking')"
                                            class="px-2.5 py-1 rounded bg-surface border border-border hover:bg-canvas font-semibold text-[11px] cursor-pointer">Unit
                                            Booking</button>
                                    @endif
                                    @if($actionStage === 'booking')
                                        <button type="button" wire:click="$set('targetNextStage', 'payment_received')"
                                            class="px-2.5 py-1 rounded bg-surface border border-border hover:bg-canvas font-semibold text-[11px] cursor-pointer">Payment</button>
                                    @endif
                                </div>

                            @endif
                            <!-- Negative Outcome Controls (Lost / Invalid) -->
                            <div class="flex items-center gap-1.5 ml-auto">
                                <button type="button" wire:click="$set('targetNextStage', 'lost_form')"
                                    class="px-2 py-1 rounded text-neutral-600 hover:text-red-700 hover:bg-red-50 text-[11px] font-semibold transition cursor-pointer">
                                    Mark Lost
                                </button>
                                <button type="button" wire:click="$set('targetNextStage', 'invalid_form')"
                                    class="px-2 py-1 rounded text-neutral-600 hover:text-amber-700 hover:bg-amber-50 text-[11px] font-semibold transition cursor-pointer">
                                    Invalid / Unreachable
                                </button>
                            </div>
                        </div>
                    @endif

                    @if($isSalesExecutive && in_array($normalizedStage, ['payment_received', 'closed_won']))
                        @foreach($latestBooking?->payments ?? [] as $payment)
                            <p class="text-xs">Payment {{ $payment->amount }} · {{ $payment->paid_at }} ·
                        {{ $payment->transaction_id }} · {{ $payment->method }}</p>@endforeach
                    @endif
                    @if(in_array($normalizedStage, ['lost', 'invalid', 'unreachable']))
                        @foreach($activityLogs->where('action', 'lead.stage_changed')->take(1) as $entry)
                        <p class="text-xs">{{ data_get(json_decode($entry->to_value, true), 'remarks') }}</p>@endforeach
                    @endif
                    @if($isSalesExecutive && $normalizedStage === 'payment_received')
                        <div class="space-y-2"><label class="text-xs font-semibold">Payment Proof (PDF, JPG or
                                PNG)</label><input type="file" wire:model="paymentProof"
                                accept=".pdf,.jpg,.jpeg,.png"><x-ui.button wire:click="uploadPaymentProofAction">Upload Payment
                                Proof</x-ui.button></div>
                    @endif
                    <!-- Lost Form Panel -->
                    @if($targetNextStage === 'lost_form')
                        <div class="p-3 bg-red-50/70 rounded-lg border border-red-200 text-xs space-y-2 mt-2">
                            <span class="font-bold text-red-900 block">Mark Lead as Lost (Mandatory Fields)</span>
                            <div>
                                <label class="text-[10px] font-semibold text-red-800">Lost Reason <span
                                        class="text-red-500">*</span></label>
                                <x-ui.themed-select class="w-full" searchable  wire:model="lostReason"
                                    trigger-class="w-full text-xs p-1.5 rounded border border-red-300 bg-surface text-ink mt-0.5">
                                    <option value="Budget mismatch">Budget mismatch</option>
                                    <option value="Location preference mismatch">Location preference mismatch</option>
                                    <option value="Purchased competitor property">Purchased competitor property</option>
                                    <option value="Postponed purchase plan">Postponed purchase plan</option>
                                    <option value="Loan eligibility issue">Loan eligibility issue</option>
                                    <option value="Other">Other</option>
                                </x-ui.themed-select>
                            </div>
                            <div>
                                <label class="text-[10px] font-semibold text-red-800">Detailed Remarks <span
                                        class="text-red-500">*</span></label>
                                <textarea wire:model="lostRemarks" rows="2"
                                    placeholder="Explain why the customer decided not to proceed..."
                                    class="w-full text-xs p-1.5 rounded border border-red-300 bg-surface text-ink mt-0.5"></textarea>
                            </div>
                            <div class="flex items-center justify-end gap-2 pt-1">
                                <button type="button" wire:click="$set('targetNextStage', '')"
                                    class="text-[11px] text-muted hover:underline">Cancel</button>
                                <button type="button" wire:click="markLostAction"
                                    class="px-3 py-1 bg-red-600 hover:bg-red-700 text-white font-bold text-xs rounded cursor-pointer">Confirm
                                    Lost</button>
                            </div>
                        </div>
                    @endif

                    <!-- Invalid / Unreachable Form Panel -->
                    @if($targetNextStage === 'invalid_form')
                        <div class="p-3 bg-amber-50/70 rounded-lg border border-amber-200 text-xs space-y-2 mt-2">
                            <span class="font-bold text-amber-900 block">Mark Lead as Invalid / Unreachable</span>
                            <div>
                                <label class="text-[10px] font-semibold text-amber-800">Verification Outcome</label>
                                <x-ui.themed-select class="w-full" searchable  wire:model="lostReason"
                                    trigger-class="w-full text-xs p-1.5 rounded border border-amber-300 bg-surface text-ink mt-0.5">
                                    <option value="Unreachable after 3+ attempts">Unreachable after 3+ attempts</option>
                                    <option value="Wrong / Invalid Phone Number">Wrong / Invalid Phone Number</option>
                                    <option value="Duplicate Lead">Duplicate Lead</option>
                                    <option value="Not Inquired / Spam">Not Inquired / Spam</option>
                                </x-ui.themed-select>
                            </div>
                            <div>
                                <label class="text-[10px] font-semibold text-amber-800">Remarks <span
                                        class="text-red-500">*</span></label>
                                <textarea wire:model="lostRemarks" rows="2" placeholder="Call attempt timestamps and notes..."
                                    class="w-full text-xs p-1.5 rounded border border-amber-300 bg-surface text-ink mt-0.5"></textarea>
                            </div>
                            <div class="flex items-center justify-end gap-2 pt-1">
                                <button type="button" wire:click="$set('targetNextStage', '')"
                                    class="text-[11px] text-muted hover:underline">Cancel</button>
                                <button type="button" wire:click="markInvalidAction('invalid')"
                                    class="px-3 py-1 bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs rounded cursor-pointer">Confirm
                                    Invalid</button>
                            </div>
                        </div>
                    @endif
                @endif
            </div>

            <!-- ========================================================= -->
            <!-- SECTION 7: FOLLOW-UP INFORMATION                          -->
            <!-- ========================================================= -->
            <div class="p-4 bg-canvas rounded-xl border border-border space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <span class="text-[10px] font-bold text-muted uppercase tracking-wider block">5 &bull; Follow-up
                        Schedule &amp; History</span>
                    <span class="font-bold text-ink text-[11px]">{{ count($leadFollowups) }} Recorded</span>
                </div>

                <div class="space-y-1.5 max-h-36 overflow-y-auto">
                    @forelse($leadFollowups as $fu)
                        <div
                            class="p-2 rounded bg-surface border border-border flex items-center justify-between gap-2 text-[11px]">
                            <div class="min-w-0 flex-1">
                                <span
                                    class="font-bold text-ink block truncate">{{ $fu->note ?: 'Scheduled customer follow-up' }}</span>
                                <span
                                    class="text-muted font-mono text-[10px]">{{ \Carbon\Carbon::parse($fu->due_at)->format('d M Y, H:i') }}</span>
                            </div>
                            <div class="flex items-center gap-1.5 shrink-0">
                                @if($fu->status === 'completed')
                                    <span
                                        class="px-2 py-0.5 rounded text-[9px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Done</span>
                                @else
                                    <span
                                        class="px-2 py-0.5 rounded text-[9px] font-bold bg-amber-50 text-amber-700 border border-amber-200">Pending</span>
                                    @if(!$isBuilder && (!$isSalesExecutive || in_array('completeFollowupAction', $salesActions)))<button
                                        type="button" wire:click="completeFollowupAction({{ $fu->id }})"
                                    class="text-[10px] font-bold text-primary hover:underline cursor-pointer">Complete</button>@endif
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="py-3 text-center text-muted text-xs">No follow-ups recorded yet.</div>
                    @endforelse
                </div>
            </div>

            <!-- ========================================================= -->
            <!-- BUILDER / DEVELOPER ONLY: RETURN LEAD / REPLACEMENT      -->
            <!-- ========================================================= -->
            @if($isBuilder)
                <div class="p-4 bg-rose-50/50 rounded-xl border border-rose-200 space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 15v-1a4 4 0 00-4-4H4m0 0l4-4m-4 4l4 4m6 4v1a3 3 0 003 3h3a3 3 0 003-3V7a3 3 0 00-3-3h-3a3 3 0 00-3 3v1" />
                            </svg>
                            <span class="text-xs font-bold text-rose-900 uppercase tracking-wider">Return Lead to Lead
                                Panther (Replacement Claim)</span>
                        </div>
                        @if($lead->replacement_status === 'pending')
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800">Pending
                                Review</span>
                        @elseif($lead->replacement_status === 'rejected' || $latestReplacement?->status === 'rejected')
                            <span class="text-xs font-bold text-red-700">Rejected</span>
                        @elseif($lead->replacement_status === 'approved')
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800">Refund
                                Approved</span>
                        @endif
                    </div>

                    @if($lead->replacement_status !== 'pending' && $lead->replacement_status !== 'approved' && !in_array($normalizedStage, ['closed_won', 'booking', 'payment_received', 'replaced', 'pending_credit']))
                        <p class="text-[11px] text-rose-700 leading-relaxed">
                            If this lead is invalid, duplicate, or unreachable within policy SLA, submit a replacement claim to
                            Super Admin for credit refund.
                        </p>
                        <div class="space-y-2">
                            <div>
                                <label class="text-[10px] font-semibold text-rose-900 block">Select Replacement Reason <span
                                        class="text-rose-600">*</span></label>
                                <x-ui.themed-select class="w-full" searchable  wire:model="replacementReasonId"
                                    trigger-class="w-full text-xs p-2 rounded-lg border border-rose-200 bg-surface text-ink mt-0.5">
                                    <option value="">-- Choose Reason --</option>
                                    @foreach($replacementReasons as $rr)
                                        <option value="{{ $rr->id }}">{{ $rr->label }}</option>
                                    @endforeach
                                </x-ui.themed-select>
                            </div>
                            <div>
                                <label class="text-[10px] font-semibold text-rose-900 block">Detailed Remarks for Super
                                    Admin</label>
                                <textarea wire:model="replacementRemarks" rows="2"
                                    placeholder="Call attempts made, customer feedback, reasons..."
                                    class="w-full text-xs p-2 rounded-lg border border-rose-200 bg-surface text-ink mt-0.5"></textarea>
                            </div>
                            <textarea wire:model="replacementSupportingInfo" rows="2"
                                placeholder="Supporting information (optional)"
                                class="w-full text-xs p-2 rounded-lg border border-rose-200 bg-surface text-ink"></textarea>
                            <button type="button" wire:click="requestReplacement"
                                class="w-full py-2 bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs rounded-lg transition shadow-2xs cursor-pointer">
                                Request Replacement
                            </button>
                        </div>
                    @else
                        <div class="p-2.5 rounded bg-surface border border-rose-200 text-[11px] text-ink">
                            A replacement request has been submitted to Lead Panther Super Admin. You will receive credits back
                            into your wallet once approved.
                        </div>
                    @endif
                </div>
            @endif
            @endif

            <!-- ============================================================= -->
            <!-- SECTION 8: ACTIVITY TIMELINE (TAB 2)                         -->
            <!-- ============================================================= -->
            @if($activeTab === 'timeline')
                <div class="space-y-3">
                    <div class="flex items-center justify-between pb-1">
                        <span class="text-xs font-bold text-ink uppercase tracking-wider">Lead Activity Trail &amp; Status
                            Changes</span>
                        @if(auth()->user()?->status === 'active' && auth()->user()->hasAnyRole(['Super Admin', 'super-admin']))
                        <button type="button" wire:click="showFullJourney"
                            class="text-xs font-semibold text-primary hover:underline cursor-pointer">Expand Lead Journey
                            &rarr;</button>
                        @endif
                    </div>

                    @foreach($activityLogs as $entry)
                        <div class="p-3 bg-canvas rounded-lg border border-border text-xs space-y-1">
                            <strong>{{ \App\Support\ClientFacingText::label($entry->action) }}</strong>
                            <p>{{ $entry->user?->name ?? 'System' }} · {{ $entry->created_at }}</p>
                            <p class="break-words">Previous: {{ \App\Support\ClientFacingText::activity($entry->from_value ?: '—') }}</p>
                            <p class="break-words">New: {{ \App\Support\ClientFacingText::activity($entry->to_value ?: '—') }}</p>
                        </div>
                    @endforeach
                    <!-- Stage History -->
                    <div class="space-y-2">
                        @forelse($statusHistory as $sh)
                            <div class="p-3 bg-canvas rounded-lg border border-border text-xs flex items-start gap-3">
                                <div
                                    class="w-6 h-6 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center shrink-0 mt-0.5">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                    </svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="font-bold text-ink">Stage changed to
                                            {{ ucfirst(str_replace('_', ' ', $sh->to_status)) }}</span>
                                        <span class="text-muted text-[10px]">(from
                                            {{ ucfirst(str_replace('_', ' ', $sh->from_status)) }})</span>
                                    </div>
                                    <div class="text-[10px] text-muted font-mono mt-0.5">
                                        By {{ $sh->changer?->name ?? 'User #' . $sh->changed_by }} &bull;
                                        {{ \Carbon\Carbon::parse($sh->changed_at)->format('d M Y, H:i') }}
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="py-4 text-center text-muted text-xs">No stage history records logged yet.</div>
                        @endforelse

                        <!-- Call Logs -->
                        @foreach($leadCalls as $call)
                            <div class="p-3 bg-canvas rounded-lg border border-border text-xs flex items-start gap-3">
                                <div
                                    class="w-6 h-6 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0 mt-0.5">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                    </svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="font-bold text-ink">Call Logged: {{ $call->outcome }}</div>
                                    <div class="text-[10px] text-muted font-mono mt-0.5">
                                        Duration: {{ (int) ($call->duration_seconds / 60) }} min &bull;
                                        {{ \Carbon\Carbon::parse($call->called_at)->format('d M Y, H:i') }}
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- ============================================================= -->
            <!-- COMMUNICATIONS (TAB 3)                                       -->
            <!-- ============================================================= -->
            @if($activeTab === 'communications')
                <div class="space-y-3">
                    <span class="text-xs font-bold text-ink uppercase tracking-wider block">Customer Messages &amp;
                        Dispatches</span>
                    <div class="space-y-2">
                        @forelse($communications as $comm)
                            <div class="p-3 bg-canvas rounded-lg border border-border space-y-1 text-xs">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <span class="font-bold text-ink uppercase text-[10px]">{{ $comm->channel }}</span>
                                    <span
                                        class="text-muted font-mono text-[10px]">{{ \Carbon\Carbon::parse($comm->sent_at)->format('M d, H:i') }}</span>
                                </div>
                                <p class="text-ink leading-relaxed">{{ $comm->message }}</p>
                            </div>
                        @empty
                            <div class="py-6 text-center text-muted text-xs">No communications dispatched yet.</div>
                        @endforelse
                    </div>
                </div>
            @endif

        </div>

        <!-- 3. DRAWER FOOTER -->
        <div class="p-4 border-t border-border bg-canvas flex items-center justify-between sticky bottom-0 z-10">
            <span class="text-[11px] font-mono text-muted">ID: #{{ $lead->id }}</span>
            <x-ui.button wire:click="close" variant="secondary" class="text-xs">
                Close Drawer
            </x-ui.button>
        </div>
    </div>
    @endif

    <!-- Assign Follow-Up Modal -->
    @if($showAssignFollowupModal)
        <div class="fixed inset-0 bg-ink/40 backdrop-blur-xs z-50 flex items-center justify-center p-4 overflow-y-auto overscroll-contain">
            <div class="bg-surface rounded-card border border-border p-6 shadow-xl max-w-md w-full space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-ink">Assign Follow-Up to Executive</h3>
                    <button type="button" wire:click="$set('showAssignFollowupModal', false)" class="text-muted hover:text-ink text-sm cursor-pointer">&times;</button>
                </div>
                <p class="text-xs text-muted">Assign a follow-up task to a team executive while keeping lead ownership unchanged.</p>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="font-bold text-ink">Select Executive <span class="text-rose-500">*</span></label>
                        <x-ui.themed-select wire:model="assigneeId" placeholder="-- Choose Executive --" :searchable="true" class="w-full mt-1">
                            <option value="">-- Choose Executive --</option>
                            @foreach($this->selectableExecutives as $exec)
                                <option value="{{ $exec->id }}">{{ $exec->name }} ({{ $exec->roles->pluck('name')->first() ?? 'Executive' }})</option>
                            @endforeach
                        </x-ui.themed-select>
                        @error('assigneeId') <span class="text-rose-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="font-bold text-ink">Action Type <span class="text-rose-500">*</span></label>
                            <x-ui.themed-select wire:model="assignType" placeholder="Action Type" class="w-full mt-1">
                                <option value="call">Phone Call</option>
                                <option value="whatsapp">WhatsApp Message</option>
                                <option value="meeting">Face-to-Face Meeting</option>
                                <option value="site_visit">Site Visit</option>
                                <option value="other">Other Action</option>
                            </x-ui.themed-select>
                        </div>
                        <div>
                            <label class="font-bold text-ink">Date &amp; Time <span class="text-rose-500">*</span></label>
                            <input type="datetime-local" wire:model="assignDueAt" class="w-full p-2.5 rounded-lg border border-border bg-canvas text-ink mt-1">
                            @error('assignDueAt') <span class="text-rose-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="font-bold text-ink">Follow-up Instructions / Requirements</label>
                        <textarea wire:model="assignNote" rows="3" placeholder="Specify instructions, customer requirements, or key questions for the executive..."
                            class="w-full p-2.5 rounded-lg border border-border bg-canvas text-ink mt-1"></textarea>
                        @error('assignNote') <span class="text-rose-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-border">
                    <button type="button" wire:click="$set('showAssignFollowupModal', false)" class="px-3 py-1.5 rounded-lg border border-border text-xs font-semibold text-ink hover:bg-canvas cursor-pointer">
                        Cancel
                    </button>
                    <button type="button" wire:click="saveAssignFollowup" class="px-4 py-1.5 rounded-lg bg-primary hover:bg-primary-dark text-white font-bold text-xs cursor-pointer shadow-xs">
                        Assign Follow-Up
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Reassign Follow-Up Modal -->
    @if($showReassignFollowupModal)
        <div class="fixed inset-0 bg-ink/40 backdrop-blur-xs z-50 flex items-center justify-center p-4 overflow-y-auto overscroll-contain">
            <div class="bg-surface rounded-card border border-border p-6 shadow-xl max-w-md w-full space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-ink">Reassign Follow-Up</h3>
                    <button type="button" wire:click="$set('showReassignFollowupModal', false)" class="text-muted hover:text-ink text-sm cursor-pointer">&times;</button>
                </div>
                <p class="text-xs text-muted">Delegate this pending task to another executive. The lead owner will remain unchanged.</p>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="font-bold text-ink">New Responsible Executive <span class="text-rose-500">*</span></label>
                        <x-ui.themed-select wire:model="reassigneeId" placeholder="-- Choose Executive --" :searchable="true" class="w-full mt-1">
                            <option value="">-- Choose Executive --</option>
                            @foreach($this->selectableExecutives as $exec)
                                <option value="{{ $exec->id }}">{{ $exec->name }}</option>
                            @endforeach
                        </x-ui.themed-select>
                        @error('reassigneeId') <span class="text-rose-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="font-bold text-ink">Adjust Scheduled Time (Optional)</label>
                        <input type="datetime-local" wire:model="reassignDueAt" class="w-full p-2.5 rounded-lg border border-border bg-canvas text-ink mt-1">
                    </div>

                    <div>
                        <label class="font-bold text-ink">Instructions / Notes</label>
                        <textarea wire:model="reassignNote" rows="2" class="w-full p-2.5 rounded-lg border border-border bg-canvas text-ink mt-1"></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-border">
                    <button type="button" wire:click="$set('showReassignFollowupModal', false)" class="px-3 py-1.5 rounded-lg border border-border text-xs font-semibold text-ink hover:bg-canvas cursor-pointer">
                        Cancel
                    </button>
                    <button type="button" wire:click="saveReassignFollowup" class="px-4 py-1.5 rounded-lg bg-primary hover:bg-primary-dark text-white font-bold text-xs cursor-pointer shadow-xs">
                        Confirm Reassignment
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Request Follow-Up Modal -->
    @if($showRequestFollowupModal)
        <div class="fixed inset-0 bg-ink/40 backdrop-blur-xs z-50 flex items-center justify-center p-4 overflow-y-auto overscroll-contain">
            <div class="bg-surface rounded-card border border-border p-6 shadow-xl max-w-md w-full space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-ink">Request Follow-Up from Executive</h3>
                    <button type="button" wire:click="$set('showRequestFollowupModal', false)" class="text-muted hover:text-ink text-sm cursor-pointer">&times;</button>
                </div>
                <p class="text-xs text-muted">Formally request a follow-up action with specific instructions and a required deadline.</p>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="font-bold text-ink">Responsible Executive <span class="text-rose-500">*</span></label>
                        <x-ui.themed-select wire:model="requestAssigneeId" placeholder="-- Choose Executive --" :searchable="true" class="w-full mt-1">
                            <option value="">-- Choose Executive --</option>
                            @foreach($this->selectableExecutives as $exec)
                                <option value="{{ $exec->id }}">{{ $exec->name }} ({{ $exec->roles->pluck('name')->first() ?? 'Executive' }})</option>
                            @endforeach
                        </x-ui.themed-select>
                        @error('requestAssigneeId') <span class="text-rose-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="font-bold text-ink">Action Type <span class="text-rose-500">*</span></label>
                            <x-ui.themed-select wire:model="requestType" placeholder="Action Type" class="w-full mt-1">
                                <option value="call">Phone Call</option>
                                <option value="whatsapp">WhatsApp Message</option>
                                <option value="meeting">Face-to-Face Meeting</option>
                                <option value="site_visit">Site Visit</option>
                                <option value="other">Other Action</option>
                            </x-ui.themed-select>
                        </div>
                        <div>
                            <label class="font-bold text-ink">Target Deadline <span class="text-rose-500">*</span></label>
                            <input type="datetime-local" wire:model="requestDueAt" class="w-full p-2.5 rounded-lg border border-border bg-canvas text-ink mt-1">
                            @error('requestDueAt') <span class="text-rose-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="font-bold text-ink">Follow-Up Instructions / Customer Requirements <span class="text-rose-500">*</span></label>
                        <textarea wire:model="requestNote" rows="3" placeholder="Provide clear instructions on customer needs, quotation details, or required questions..."
                            class="w-full p-2.5 rounded-lg border border-border bg-canvas text-ink mt-1"></textarea>
                        @error('requestNote') <span class="text-rose-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-border">
                    <button type="button" wire:click="$set('showRequestFollowupModal', false)" class="px-3 py-1.5 rounded-lg border border-border text-xs font-semibold text-ink hover:bg-canvas cursor-pointer">
                        Cancel
                    </button>
                    <button type="button" wire:click="saveRequestFollowup" class="px-4 py-1.5 rounded-lg bg-primary hover:bg-primary-dark text-white font-bold text-xs cursor-pointer shadow-xs">
                        Submit Follow-Up Request
                    </button>
                </div>
            </div>
        </div>
    @endif
    @if(auth()->user()?->hasAnyRole(['Super Admin','super-admin']))
        <livewire:leads.lead-archive-dialog />
    @endif
</div>
