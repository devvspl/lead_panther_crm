<div>
    @if($isOpen && $lead)
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-ink/50 backdrop-blur-xs z-50 transition-opacity" wire:click="close"></div>

        <!-- Slide-over Drawer -->
        <div class="fixed inset-y-0 right-0 max-w-2xl w-full bg-surface shadow-2xl z-50 overflow-y-auto flex flex-col justify-between border-l border-border">
            <!-- 1. DRAWER HEADER -->
            <div class="p-5 border-b border-border flex items-center justify-between bg-canvas sticky top-0 z-10">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="px-2 py-0.5 text-[10px] font-bold font-mono rounded bg-ink text-white">{{ $lead->lead_code }}</span>
                        <x-ui.badge status="{{ $lead->current_stage }}">{{ ucfirst(str_replace('_', ' ', $lead->current_stage)) }}</x-ui.badge>
                        @if($lead->replacement_status === 'pending')
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-300">
                                Replacement Pending
                            </span>
                        @elseif($lead->replacement_status === 'rejected' || $latestReplacement?->status === 'rejected')
                                    <span class="text-xs font-bold text-red-700">Rejected</span>
                                @elseif($lead->replacement_status === 'approved')
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-300">
                                Replacement Approved
                            </span>
                        @endif
                    </div>
                    <h2 class="text-lg font-bold text-ink mt-1 truncate">{{ $lead->name }}</h2>
                    <p class="text-xs text-muted truncate">
                        {{ $lead->project?->name ?: 'Direct' }} &bull; {{ $lead->city ?: 'N/A' }} &bull; ₹{{ number_format($lead->budget / 100000, 1) }}L
                    </p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <button type="button" wire:click="showFullJourney" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 transition shadow-2xs cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span class="hidden sm:inline">Lead Journey</span>
                    </button>
                    <button type="button" wire:click="close" class="text-muted hover:text-ink p-1.5 rounded-lg hover:bg-surface cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>

            <!-- 2. DRAWER BODY CONTENT -->
            <div class="p-5 flex-1 space-y-5 text-xs">

                <!-- TABS NAVIGATION -->
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

                @foreach($errors->all() as $error)<p class="text-xs text-red-700" role="alert">{{ $error }}</p>@endforeach
                @if($activeTab === 'stage_details')
                    <!-- ========================================================= -->
                    <!-- SECTION 1 & 2: CUSTOMER & PROJECT INFORMATION             -->
                    <!-- ========================================================= -->
                    <div class="p-4 bg-canvas rounded-xl border border-border space-y-3">
                        <span class="text-[10px] font-bold text-muted uppercase tracking-wider block">1 &bull; Customer &amp; Project Info</span>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            <div>
                                <span class="text-[10px] text-muted block">Phone / Mobile</span>
                                <div class="flex items-center gap-1.5 mt-0.5">
                                    <span class="font-mono font-semibold text-ink">{{ $lead->mobile }}</span>
                                    <a href="tel:{{ $lead->mobile }}" class="text-primary hover:text-primary-dark" title="Call Customer">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                    </a>
                                    <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $lead->mobile) }}" target="_blank" class="text-emerald-600 hover:text-emerald-700" title="WhatsApp Chat">
                                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                                    </a>
                                </div>
                            </div>
                            <div>
                                <span class="text-[10px] text-muted block">Email Address</span>
                                <span class="font-semibold text-ink truncate block mt-0.5">{{ $lead->email ?: 'N/A' }}</span>
                            </div>
                            <div>
                                <span class="text-[10px] text-muted block">City / Location</span>
                                <span class="font-semibold text-ink block mt-0.5">{{ $lead->city ?: 'N/A' }} {{ $lead->location ? '('.$lead->location.')' : '' }}</span>
                            </div>
                            <div>
                                <span class="text-[10px] text-muted block">Project</span>
                                <span class="font-bold text-ink block mt-0.5">{{ $lead->project?->name ?: 'Direct Inbound' }}</span>
                            </div>
                            <div>
                                <span class="text-[10px] text-muted block">Property Type</span>
                                <span class="font-semibold text-ink block mt-0.5">{{ $lead->property_type ?: 'Residential' }}</span>
                            </div>
                            <div>
                                <span class="text-[10px] text-muted block">Budget</span>
                                <span class="font-bold text-ink block mt-0.5">₹{{ number_format($lead->budget / 100000, 1) }} Lakhs</span>
                            </div>
                        </div>

                        @if($lead->requirement)
                            <div class="pt-2 border-t border-border">
                                <span class="text-[10px] font-bold text-muted uppercase block">Customer Requirement / Inquiry:</span>
                                <p class="text-xs text-ink mt-0.5 leading-relaxed bg-surface p-2.5 rounded-lg border border-border">
                                    {{ $lead->requirement }}
                                </p>
                            </div>
                        @endif
                    </div>

                    <!-- ========================================================= -->
                    <!-- SECTION 3: LEAD SOURCE & META INTELLIGENCE               -->
                    <!-- ========================================================= -->
                    <div class="p-4 bg-canvas rounded-xl border border-border space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-bold text-muted uppercase tracking-wider block">2 &bull; Lead Source &amp; Ingestion</span>
                            <span class="text-[10px] font-mono text-muted">Received: {{ $lead->created_at?->format('M d, Y H:i') }}</span>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            <div>
                                <span class="text-[10px] text-muted block">Source Channel</span>
                                <span class="font-semibold text-ink block mt-0.5">{{ $lead->leadSource?->name ?: 'Direct / Portal' }}</span>
                            </div>
                            <div>
                                <span class="text-[10px] text-muted block">Campaign</span>
                                <span class="font-semibold text-ink block mt-0.5 truncate">{{ $lead->campaign?->name ?: 'Standard' }}</span>
                            </div>
                            <div>
                                <span class="text-[10px] text-muted block">Lead Cost</span>
                                <span class="font-mono font-semibold text-ink block mt-0.5">{{ number_format($lead->lead_cost, 2) }} Cr</span>
                            </div>
                        </div>

                        @php
                            $metaCamp = $lead->getMetaAttribute('meta_campaign_name') ?: $lead->campaign?->name;
                            $metaAdset = $lead->getMetaAttribute('meta_adset_name');
                            $metaAd = $lead->getMetaAttribute('meta_ad_name');
                            $metaForm = $lead->getMetaAttribute('meta_form_name');
                        @endphp
                        @if($metaCamp || $metaAdset || $metaAd || $metaForm)
                            <div class="mt-2 p-2.5 bg-blue-50/70 rounded-lg border border-blue-200 text-[11px] space-y-1">
                                <span class="font-bold text-blue-900 block text-[10px] uppercase">Meta Ads Ingestion Context:</span>
                                <div class="grid grid-cols-2 gap-2 text-ink">
                                    @if($metaCamp) <div><span class="text-muted text-[10px]">Campaign:</span> <span class="font-semibold truncate block">{{ $metaCamp }}</span></div> @endif
                                    @if($metaAdset) <div><span class="text-muted text-[10px]">Ad Set:</span> <span class="font-semibold truncate block">{{ $metaAdset }}</span></div> @endif
                                    @if($metaAd) <div><span class="text-muted text-[10px]">Creative:</span> <span class="font-semibold truncate block">{{ $metaAd }}</span></div> @endif
                                    @if($metaForm) <div><span class="text-muted text-[10px]">Form:</span> <span class="font-semibold truncate block">{{ $metaForm }}</span></div> @endif
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- ========================================================= -->
                    <!-- SECTION 4: ASSIGNMENT INFORMATION                         -->
                    <!-- ========================================================= -->
                    <div class="p-4 bg-canvas rounded-xl border border-border space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-bold text-muted uppercase tracking-wider block">3 &bull; Assignment &amp; Ownership</span>
                            @if($lead->assignedTo)
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                    Assigned
                                </span>
                            @else
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                    Unassigned
                                </span>
                            @endif
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            <div>
                                <span class="text-[10px] text-muted block">Assigned Executive</span>
                                <span class="font-bold text-ink block mt-0.5">{{ $lead->assignedTo?->name ?: 'Unassigned' }}</span>
                            </div>
                            <div>
                                <span class="text-[10px] text-muted block">Executive Email</span>
                                <span class="font-mono text-ink block mt-0.5 truncate">{{ $lead->assignedTo?->email ?: '—' }}</span>
                            </div>
                            <div>
                                <span class="text-[10px] text-muted block">Organization</span>
                                <span class="font-semibold text-ink block mt-0.5">{{ $lead->client?->organization?->name ?: 'Default' }}</span>
                            </div>
                        </div>

                        @if(!empty($assignmentHistory) && count($assignmentHistory) > 0)
                            <div class="pt-2 border-t border-border">
                                <span class="text-[10px] font-bold text-muted uppercase block mb-1">Assignment Trail:</span>
                                <div class="space-y-1 max-h-24 overflow-y-auto text-[11px]">
                                    @foreach($assignmentHistory as $asgn)
                                        <div class="flex items-center justify-between p-1.5 rounded bg-surface border border-border">
                                            <span>Assigned to <strong class="text-ink">{{ $asgn->assignee?->name ?? 'User #'.$asgn->assigned_to }}</strong> by {{ $asgn->assigner?->name ?? 'System' }}</span>
                                            <span class="text-muted font-mono text-[10px]">{{ \Carbon\Carbon::parse($asgn->assigned_at)->format('M d, H:i') }}</span>
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
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-bold text-muted uppercase tracking-wider block">4 &bull; Current Stage &amp; SLA</span>
                            <span class="text-xs font-bold text-primary">{{ ucfirst(str_replace('_', ' ', $lead->current_stage)) }}</span>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            <div>
                                <span class="text-[10px] text-muted block">First Response</span>
                                <span class="font-semibold text-ink block mt-0.5">
                                    {{ $lead->first_response_at ? \Carbon\Carbon::parse($lead->first_response_at)->format('M d, H:i') : 'Pending Action' }}
                                </span>
                            </div>
                            <div>
                                <span class="text-[10px] text-muted block">SLA Compliance</span>
                                <div class="mt-0.5">
                                    <x-ui.badge status="{{ $lead->sla_badge }}">{{ $lead->sla_badge }}</x-ui.badge>
                                </div>
                            </div>
                            <div>
                                <span class="text-[10px] text-muted block">Deal Status</span>
                                <span class="font-bold text-ink block mt-0.5">{{ ucfirst($lead->status) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- ========================================================= -->
                    <!-- SECTION 6: DYNAMIC STAGE-SPECIFIC DETAILS & ACTIONS       -->
                    <!-- ========================================================= -->
                    <div class="p-4 rounded-xl border-2 border-primary/20 bg-primary/5 space-y-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-xs font-bold text-ink uppercase tracking-wider flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-primary"></span>
                                    <span>Stage Actions: {{ ucfirst(str_replace('_', ' ', $lead->current_stage)) }}</span>
                                </h3>
                                <p class="text-[11px] text-muted mt-0.5">Available sales operations for the current pipeline state</p>
                            </div>
                            <x-ui.badge status="{{ $lead->current_stage }}">{{ ucfirst(str_replace('_', ' ', $lead->current_stage)) }}</x-ui.badge>
                        </div>

                        @if($isBuilder)
                            <div class="text-xs space-y-2">
                                <p>Requirements: {{ $lead->requirement ?: '—' }} · Budget: {{ $lead->budget ?: '—' }}</p>
                                @if($normalizedStage === 'follow_up')@foreach($leadFollowups as $followup)<p>{{ $followup->due_at }} · {{ $followup->note }} · {{ $followup->status }}</p>@endforeach @endif
                                @if($normalizedStage === 'meeting_scheduled')<p>{{ $latestMeeting?->scheduled_at }} · {{ $latestMeeting?->location }} · {{ $latestMeeting?->outcome }}</p><p>{{ $latestMeeting?->notes }}</p>@endif
                                @if($normalizedStage === 'site_visit')<p>{{ $latestSiteVisit?->visit_date }} · {{ $latestSiteVisit?->attendance }} · {{ $latestSiteVisit?->outcome }}</p><p>{{ $latestSiteVisit?->remarks }}</p>@endif
                                @if($normalizedStage === 'negotiation')<p>{{ $latestNegotiation?->notes }}</p>@endif
                                @if(in_array($normalizedStage,['booking','payment_received','closed_won']))<p>Unit: {{ $latestBooking?->unit?->unit_number }} · Booking: {{ $latestBooking?->booking_amount }} · {{ $latestBooking?->booked_at }}</p>@foreach($latestBooking?->payments ?? [] as $payment)<p>Payment: {{ $payment->amount }} · {{ $payment->paid_at }} · {{ $payment->transaction_id }}</p>@endforeach @endif
                                @if(!in_array($normalizedStage,['closed_won','lost','invalid','unreachable','replaced','pending_credit']))<x-ui.button x-on:click="$dispatch('open-lead-assign', { id: {{ $lead->id }} })" variant="secondary">{{ $lead->assigned_to ? 'Reassign Lead' : 'Assign Lead' }}</x-ui.button>@endif
                            </div>
                        @else
                        @php
                            $nextForms=['follow_up'=>'meeting_scheduled','meeting_scheduled'=>'site_visit','site_visit'=>'negotiation','negotiation'=>'booking','booking'=>'payment_received'];
                            $actionStage=\App\Models\Lead::usesSalesPanel() ? $normalizedStage : $lead->current_stage;
                            if($isSalesExecutive && ($nextForms[$normalizedStage] ?? null)===$targetNextStage) $actionStage=$targetNextStage;
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
                                        <select wire:model="callOutcome" class="w-full text-xs p-2 rounded-lg border border-border bg-canvas text-ink mt-0.5">
                                            <option value="Connected">Connected &amp; Discussed</option>
                                            <option value="Interested">Interested in Project</option>
                                            <option value="Call Back Later">Call Back Later / Busy</option>
                                            <option value="Ringing No Response">Ringing No Response</option>
                                            <option value="Switch Off / Unreachable">Switch Off / Unreachable</option>
                                            <option value="Wrong Number">Wrong / Invalid Number</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="text-[10px] font-semibold text-muted">Duration (Minutes)</label>
                                        <input type="number" wire:model="callDuration" class="w-full text-xs p-2 rounded-lg border border-border bg-canvas text-ink mt-0.5" min="0">
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
                                    <label class="text-[10px] font-semibold text-muted">Customer Notes / Specific Requirements</label>
                                    <textarea wire:model="actionRemarks" rows="2" placeholder="e.g. Looking for 3 BHK high-floor facing park, ready to visit next weekend..." class="w-full text-xs p-2 rounded-lg border border-border bg-canvas text-ink mt-0.5"></textarea>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="text-[10px] font-semibold text-muted">Schedule Follow-up Date &amp; Time</label>
                                        <input type="datetime-local" wire:model="followupDueAt" class="w-full text-xs p-2 rounded-lg border border-border bg-canvas text-ink mt-0.5">
                                    </div>
                                    <div>
                                        <label class="text-[10px] font-semibold text-muted">Follow-up Call Objective</label>
                                        <input type="text" wire:model="followupNote" placeholder="Confirm site visit appointment" class="w-full text-xs p-2 rounded-lg border border-border bg-canvas text-ink mt-0.5">
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
                            <div class="space-y-3 bg-surface p-3.5 rounded-lg border border-border">
                                <span class="text-xs font-bold text-ink block">Active Follow-up Queue</span>
                                @php
                                    $activeFollowup = $lead->followups()->where('status', 'pending')->orderBy('due_at', 'asc')->first();
                                @endphp
                                @if($activeFollowup)
                                    <div class="p-2.5 rounded-lg bg-amber-50/70 border border-amber-200 text-xs flex items-center justify-between">
                                        <div>
                                            <span class="font-bold text-amber-900 block">Scheduled Follow-up Call</span>
                                            <p class="text-[11px] text-amber-800">{{ $activeFollowup->note ?: 'Follow-up discussion' }}</p>
                                        </div>
                                        <span class="font-mono text-[11px] font-bold text-amber-800 bg-white px-2 py-1 rounded border border-amber-200">
                                            {{ \Carbon\Carbon::parse($activeFollowup->due_at)->format('M d, H:i') }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2 pt-1">
                                        <button type="button" wire:click="completeFollowupAction({{ $activeFollowup->id }})"
                                            class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs flex-1 cursor-pointer">
                                            Mark Completed
                                        </button>
                                        <button type="button" wire:click="$set('completingFollowupId', {{ $activeFollowup->id }})"
                                            class="px-3 py-1.5 rounded-lg bg-surface border border-border text-ink hover:bg-canvas font-semibold text-xs cursor-pointer">
                                            Reschedule Call
                                        </button>
                                    </div>
                                @if($completingFollowupId === $activeFollowup->id)
                                    <input aria-label="New follow-up time" type="datetime-local" wire:model="followupDueAt" class="w-full text-xs rounded-lg border-border">
                                    <textarea aria-label="Follow-up remarks" wire:model="followupNote" class="w-full text-xs rounded-lg border-border"></textarea>
                                    <x-ui.button wire:click="rescheduleFollowupAction({{ $activeFollowup->id }})">Save Rescheduled Follow-up</x-ui.button>
                                @endif
                                @else
                                    <p class="text-xs text-muted">No pending follow-up scheduled. Schedule a new one below:</p>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="text-[10px] font-semibold text-muted">Date &amp; Time</label>
                                            <input type="datetime-local" wire:model="followupDueAt" class="w-full text-xs p-2 rounded-lg border border-border bg-canvas text-ink mt-0.5">
                                        </div>
                                        <div>
                                            <label class="text-[10px] font-semibold text-muted">Call Objective</label>
                                            <input type="text" wire:model="followupNote" placeholder="Next follow-up objective" class="w-full text-xs p-2 rounded-lg border border-border bg-canvas text-ink mt-0.5">
                                        </div>
                                    </div>
                                    <x-ui.button wire:click="scheduleFollowupAction" variant="primary" class="w-full text-xs">
                                        Schedule Follow-up
                                    </x-ui.button>
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
                                        <input type="datetime-local" wire:model="meetingScheduledAt" class="w-full text-xs p-2 rounded-lg border border-border bg-canvas text-ink mt-0.5">
                                    </div>
                                    <div>
                                        <label class="text-[10px] font-semibold text-muted">Location</label>
                                        <input type="text" wire:model="meetingLocation" placeholder="Site Office / Client Office" class="w-full text-xs p-2 rounded-lg border border-border bg-canvas text-ink mt-0.5">
                                    </div>
                                </div>
                                <div>
                                    <label class="text-[10px] font-semibold text-muted">Meeting Notes / Agenda</label>
                                    <textarea wire:model="meetingNotes" rows="2" placeholder="Discussion on floor plan, payment plans..." class="w-full text-xs p-2 rounded-lg border border-border bg-canvas text-ink mt-0.5"></textarea>
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
                                <textarea wire:model="visitRemarks" placeholder="Visit remarks" class="w-full text-xs rounded-lg border-border"></textarea>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="text-[10px] font-semibold text-muted">Visit Date &amp; Time</label>
                                        <input type="datetime-local" wire:model="visitDate" class="w-full text-xs p-2 rounded-lg border border-border bg-canvas text-ink mt-0.5">
                                    </div>
                                    <div>
                                        <label class="text-[10px] font-semibold text-muted">Attendance Status</label>
                                        <select wire:model="visitAttendance" class="w-full text-xs p-2 rounded-lg border border-border bg-canvas text-ink mt-0.5">
                                            <option value="attended">Attended Visit</option>
                                            @if(!\App\Models\Lead::usesSalesPanel())<option value="scheduled">Scheduled / Upcoming</option>@endif
                                            <option value="no_show">No Show / Cancelled</option>
                                        </select>
                                    </div>
                                </div>
                                <div>
                                    <label class="text-[10px] font-semibold text-muted">Customer Visit Feedback &amp; Unit Preference</label>
                                    <input type="text" wire:model="visitOutcome" placeholder="e.g. Liked 3 BHK corner unit, requested pricing sheet" class="w-full text-xs p-2 rounded-lg border border-border bg-canvas text-ink mt-0.5">
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
                        @elseif($actionStage === 'negotiation')
                            <div class="space-y-3 bg-surface p-3.5 rounded-lg border border-border">
                                <span class="text-xs font-bold text-ink block">Negotiation &amp; Commercial Terms</span>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="text-[10px] font-semibold text-muted">Quoted / Agreed Amount (₹)</label>
                                        <input type="text" wire:model="quotedAmount" placeholder="e.g. 8500000" class="w-full text-xs p-2 rounded-lg border border-border bg-canvas text-ink mt-0.5">
                                    </div>
                                    <div>
                                        <label class="text-[10px] font-semibold text-muted">Payment Plan Option</label>
                                        <select class="w-full text-xs p-2 rounded-lg border border-border bg-canvas text-ink mt-0.5">
                                            <option>Construction Linked Plan (CLP)</option>
                                            <option>Down Payment Plan (DP)</option>
                                            <option>Subvention / Flexi Plan</option>
                                        </select>
                                    </div>
                                </div>
                                <div>
                                    <label class="text-[10px] font-semibold text-muted">Negotiation Notes &amp; Special Concessions</label>
                                    <textarea wire:model="negotiationNotes" rows="2" placeholder="e.g. Offered free car parking and modular kitchen discount on booking within 3 days..." class="w-full text-xs p-2 rounded-lg border border-border bg-canvas text-ink mt-0.5"></textarea>
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
                        @elseif($actionStage === 'booking')
                            <div class="space-y-3 bg-surface p-3.5 rounded-lg border border-border">
                                <span class="text-xs font-bold text-ink block">Unit Booking Details</span>
                                @if($latestBooking)<p class="text-xs">Selected unit: {{ $latestBooking->unit?->unit_number }} · Booked: {{ $latestBooking->booked_at }}</p>@endif
                                <div class="grid grid-cols-3 gap-3">
                                    <div>
                                        <label class="text-[10px] font-semibold text-muted">Unit / Flat No.</label>
                                        <input type="text" wire:model="bookingUnit" placeholder="e.g. Tower B - 1204" class="w-full text-xs p-2 rounded-lg border border-border bg-canvas text-ink mt-0.5">
                                    </div>
                                    <div>
                                        <label class="text-[10px] font-semibold text-muted">Token / Booking Amount (₹)</label>
                                        <input type="number" wire:model="bookingAmount" placeholder="e.g. 200000" class="w-full text-xs p-2 rounded-lg border border-border bg-canvas text-ink mt-0.5">
                                    </div>
                                    <div>
                                        <label class="text-[10px] font-semibold text-muted">Booking Date</label>
                                        <input type="date" wire:model="bookingDate" class="w-full text-xs p-2 rounded-lg border border-border bg-canvas text-ink mt-0.5">
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
                        @elseif($actionStage === 'payment_received')
                            <div class="space-y-3 bg-surface p-3.5 rounded-lg border border-border">
                                <span class="text-xs font-bold text-ink block">Record Payment &amp; Verify Receipt</span>
                                <div class="grid grid-cols-3 gap-3">
                                    <div>
                                        <label class="text-[10px] font-semibold text-muted">Payment Amount (₹)</label>
                                        <input type="number" wire:model="paymentAmount" class="w-full text-xs p-2 rounded-lg border border-border bg-canvas text-ink mt-0.5">
                                    </div>
                                    <div>
                                        <label class="text-[10px] font-semibold text-muted">Payment Method</label>
                                        <select wire:model="paymentMethod" class="w-full text-xs p-2 rounded-lg border border-border bg-canvas text-ink mt-0.5">
                                            <option value="Bank Transfer">Bank Transfer / NEFT / RTGS</option>
                                            <option value="UPI">UPI / Net Banking</option>
                                            <option value="Cheque">Cheque</option>
                                            <option value="Cash">Cash / Other</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="text-[10px] font-semibold text-muted">Transaction ID / Cheque No.</label>
                                        <input type="text" wire:model="paymentTransactionId" placeholder="e.g. TXN9872134" class="w-full text-xs p-2 rounded-lg border border-border bg-canvas text-ink mt-0.5">
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
                                    This lead has completed the sales lifecycle and is fully closed won. Booking and payment records are saved in the audit ledger.
                                </p>
                                @if($latestBooking)
                                    <div class="p-2 bg-white rounded border border-emerald-200 grid grid-cols-2 gap-2 text-[11px] text-ink">
                                        <div><span class="text-muted block text-[10px]">Booking Amount:</span> <strong>₹{{ number_format($latestBooking->booking_amount) }}</strong></div>
                                        <div><span class="text-muted block text-[10px]">Booked Date:</span> <strong>{{ $latestBooking->booked_at?->format('d M Y') }}</strong></div>
                                    </div>
                                @endif
                            </div>

                        <!-- ----------------------------------------------------- -->
                        <!-- STAGE: LOST / INVALID (Read-only Summary)             -->
                        <!-- ----------------------------------------------------- -->
                        @elseif(in_array($actionStage, ['lost', 'closed_lost', 'invalid', 'unreachable']))
                            <div class="p-3.5 rounded-lg bg-neutral-100 border border-neutral-300 text-xs space-y-2">
                                <span class="font-bold text-neutral-800 block text-sm">Outcome: {{ ucfirst(str_replace('_', ' ', $actionStage)) }}</span>
                                <p class="text-[11px] text-neutral-700">
                                    This lead has been marked as {{ $actionStage }}. See activity timeline for the recorded reason and detailed remarks.
                                </p>
                            </div>
                        @endif

                        @if($isSalesExecutive && in_array('addRemarksAction',$salesActions))
                            @if($normalizedStage !== 'interested')<textarea aria-label="Customer interaction notes" wire:model="actionRemarks" rows="2" placeholder="Customer interaction notes" class="w-full text-xs rounded-lg border-border"></textarea>@endif
                            <x-ui.button wire:click="addRemarksAction" variant="secondary" class="text-xs">Add Remarks / Notes</x-ui.button>
                        @endif
                        <!-- General Stage Progression Buttons & Outcome Handlers -->
                        @if(!in_array($actionStage, ['closed_won', 'lost', 'closed_lost', 'invalid', 'unreachable', 'replaced', 'pending_credit']))
                            <div class="pt-3 border-t border-primary/20 flex items-center justify-between gap-2 flex-wrap text-xs">
                                @if($isSalesExecutive)
                                    <div class="flex gap-2 items-center">
                                        @if(isset($nextForms[$normalizedStage]))<button type="button" wire:click="$set('targetNextStage','{{ $nextForms[$normalizedStage] }}')" class="px-2.5 py-1 rounded bg-surface border border-border font-semibold text-xs">{{ ucwords(str_replace('_',' ',$nextForms[$normalizedStage])) }}</button>@endif
                                        @if($targetNextStage && !in_array($targetNextStage,['lost_form','invalid_form']))<button type="button" wire:click="$set('targetNextStage','')" class="text-xs text-muted">Back to current stage</button>@endif
                                    </div>
                                @else
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] font-bold text-muted uppercase">Advance To:</span>
                                    @if(in_array($actionStage, ['new', 'contacted', 'connected']))
                                        <button type="button" wire:click="markInterestedAction" class="px-2.5 py-1 rounded bg-surface border border-border hover:bg-canvas font-semibold text-[11px] cursor-pointer">Interested</button>
                                    @endif
                                    @if(in_array($actionStage, ['interested', 'follow_up']))
                                        <button type="button" wire:click="$set('targetNextStage', 'meeting_scheduled')" class="px-2.5 py-1 rounded bg-surface border border-border hover:bg-canvas font-semibold text-[11px] cursor-pointer">Meeting</button>
                                        <button type="button" wire:click="$set('targetNextStage', 'site_visit')" class="px-2.5 py-1 rounded bg-surface border border-border hover:bg-canvas font-semibold text-[11px] cursor-pointer">Site Visit</button>
                                    @endif
                                    @if(in_array($actionStage, ['meeting', 'meeting_scheduled', 'site_visit']))
                                        <button type="button" wire:click="$set('targetNextStage', 'negotiation')" class="px-2.5 py-1 rounded bg-surface border border-border hover:bg-canvas font-semibold text-[11px] cursor-pointer">Negotiation</button>
                                    @endif
                                    @if($actionStage === 'negotiation')
                                        <button type="button" wire:click="$set('targetNextStage', 'booking')" class="px-2.5 py-1 rounded bg-surface border border-border hover:bg-canvas font-semibold text-[11px] cursor-pointer">Unit Booking</button>
                                    @endif
                                    @if($actionStage === 'booking')
                                        <button type="button" wire:click="$set('targetNextStage', 'payment_received')" class="px-2.5 py-1 rounded bg-surface border border-border hover:bg-canvas font-semibold text-[11px] cursor-pointer">Payment</button>
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

                        @if($isSalesExecutive && in_array($normalizedStage,['payment_received','closed_won']))
                            @foreach($latestBooking?->payments ?? [] as $payment)<p class="text-xs">Payment {{ $payment->amount }} · {{ $payment->paid_at }} · {{ $payment->transaction_id }} · {{ $payment->method }}</p>@endforeach
                        @endif
                        @if(in_array($normalizedStage,['lost','invalid','unreachable']))
                            @foreach($activityLogs->where('action','lead.stage_changed')->take(1) as $entry)<p class="text-xs">{{ data_get(json_decode($entry->to_value,true),'remarks') }}</p>@endforeach
                        @endif
                        @if($isSalesExecutive && $normalizedStage === 'payment_received')
                            <div class="space-y-2"><label class="text-xs font-semibold">Payment Proof (PDF, JPG or PNG)</label><input type="file" wire:model="paymentProof" accept=".pdf,.jpg,.jpeg,.png"><x-ui.button wire:click="uploadPaymentProofAction">Upload Payment Proof</x-ui.button></div>
                        @endif
                        <!-- Lost Form Panel -->
                        @if($targetNextStage === 'lost_form')
                            <div class="p-3 bg-red-50/70 rounded-lg border border-red-200 text-xs space-y-2 mt-2">
                                <span class="font-bold text-red-900 block">Mark Lead as Lost (Mandatory Fields)</span>
                                <div>
                                    <label class="text-[10px] font-semibold text-red-800">Lost Reason <span class="text-red-500">*</span></label>
                                    <select wire:model="lostReason" class="w-full text-xs p-1.5 rounded border border-red-300 bg-white text-ink mt-0.5">
                                        <option value="Budget mismatch">Budget mismatch</option>
                                        <option value="Location preference mismatch">Location preference mismatch</option>
                                        <option value="Purchased competitor property">Purchased competitor property</option>
                                        <option value="Postponed purchase plan">Postponed purchase plan</option>
                                        <option value="Loan eligibility issue">Loan eligibility issue</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-[10px] font-semibold text-red-800">Detailed Remarks <span class="text-red-500">*</span></label>
                                    <textarea wire:model="lostRemarks" rows="2" placeholder="Explain why the customer decided not to proceed..." class="w-full text-xs p-1.5 rounded border border-red-300 bg-white text-ink mt-0.5"></textarea>
                                </div>
                                <div class="flex items-center justify-end gap-2 pt-1">
                                    <button type="button" wire:click="$set('targetNextStage', '')" class="text-[11px] text-muted hover:underline">Cancel</button>
                                    <button type="button" wire:click="markLostAction" class="px-3 py-1 bg-red-600 hover:bg-red-700 text-white font-bold text-xs rounded cursor-pointer">Confirm Lost</button>
                                </div>
                            </div>
                        @endif

                        <!-- Invalid / Unreachable Form Panel -->
                        @if($targetNextStage === 'invalid_form')
                            <div class="p-3 bg-amber-50/70 rounded-lg border border-amber-200 text-xs space-y-2 mt-2">
                                <span class="font-bold text-amber-900 block">Mark Lead as Invalid / Unreachable</span>
                                <div>
                                    <label class="text-[10px] font-semibold text-amber-800">Verification Outcome</label>
                                    <select wire:model="lostReason" class="w-full text-xs p-1.5 rounded border border-amber-300 bg-white text-ink mt-0.5">
                                        <option value="Unreachable after 3+ attempts">Unreachable after 3+ attempts</option>
                                        <option value="Wrong / Invalid Phone Number">Wrong / Invalid Phone Number</option>
                                        <option value="Duplicate Lead">Duplicate Lead</option>
                                        <option value="Not Inquired / Spam">Not Inquired / Spam</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-[10px] font-semibold text-amber-800">Remarks <span class="text-red-500">*</span></label>
                                    <textarea wire:model="lostRemarks" rows="2" placeholder="Call attempt timestamps and notes..." class="w-full text-xs p-1.5 rounded border border-amber-300 bg-white text-ink mt-0.5"></textarea>
                                </div>
                                <div class="flex items-center justify-end gap-2 pt-1">
                                    <button type="button" wire:click="$set('targetNextStage', '')" class="text-[11px] text-muted hover:underline">Cancel</button>
                                    <button type="button" wire:click="markInvalidAction('invalid')" class="px-3 py-1 bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs rounded cursor-pointer">Confirm Invalid</button>
                                </div>
                            </div>
                        @endif
                        @endif
                    </div>

                    <!-- ========================================================= -->
                    <!-- SECTION 7: FOLLOW-UP INFORMATION                          -->
                    <!-- ========================================================= -->
                    <div class="p-4 bg-canvas rounded-xl border border-border space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-bold text-muted uppercase tracking-wider block">5 &bull; Follow-up Schedule &amp; History</span>
                            <span class="font-bold text-ink text-[11px]">{{ count($leadFollowups) }} Recorded</span>
                        </div>

                        <div class="space-y-1.5 max-h-36 overflow-y-auto">
                            @forelse($leadFollowups as $fu)
                                <div class="p-2 rounded bg-surface border border-border flex items-center justify-between gap-2 text-[11px]">
                                    <div class="min-w-0 flex-1">
                                        <span class="font-bold text-ink block truncate">{{ $fu->note ?: 'Scheduled customer follow-up' }}</span>
                                        <span class="text-muted font-mono text-[10px]">{{ \Carbon\Carbon::parse($fu->due_at)->format('d M Y, H:i') }}</span>
                                    </div>
                                    <div class="flex items-center gap-1.5 shrink-0">
                                        @if($fu->status === 'completed')
                                            <span class="px-2 py-0.5 rounded text-[9px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Done</span>
                                        @else
                                            <span class="px-2 py-0.5 rounded text-[9px] font-bold bg-amber-50 text-amber-700 border border-amber-200">Pending</span>
                                            @if(!$isBuilder && (!$isSalesExecutive || in_array('completeFollowupAction',$salesActions)))<button type="button" wire:click="completeFollowupAction({{ $fu->id }})" class="text-[10px] font-bold text-primary hover:underline cursor-pointer">Complete</button>@endif
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
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 15v-1a4 4 0 00-4-4H4m0 0l4-4m-4 4l4 4m6 4v1a3 3 0 003 3h3a3 3 0 003-3V7a3 3 0 00-3-3h-3a3 3 0 00-3 3v1" /></svg>
                                    <span class="text-xs font-bold text-rose-900 uppercase tracking-wider">Return Lead to Lead Panther (Replacement Claim)</span>
                                </div>
                                @if($lead->replacement_status === 'pending')
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800">Pending Review</span>
                                @elseif($lead->replacement_status === 'rejected' || $latestReplacement?->status === 'rejected')
                                    <span class="text-xs font-bold text-red-700">Rejected</span>
                                @elseif($lead->replacement_status === 'approved')
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800">Refund Approved</span>
                                @endif
                            </div>

                            @if($lead->replacement_status !== 'pending' && $lead->replacement_status !== 'approved' && !in_array($normalizedStage,['closed_won','booking','payment_received','replaced','pending_credit']))
                                <p class="text-[11px] text-rose-700 leading-relaxed">
                                    If this lead is invalid, duplicate, or unreachable within policy SLA, submit a replacement claim to Super Admin for credit refund.
                                </p>
                                <div class="space-y-2">
                                    <div>
                                        <label class="text-[10px] font-semibold text-rose-900 block">Select Replacement Reason <span class="text-rose-600">*</span></label>
                                        <select wire:model="replacementReasonId" class="w-full text-xs p-2 rounded-lg border border-rose-200 bg-white text-ink mt-0.5">
                                            <option value="">-- Choose Reason --</option>
                                            @foreach($replacementReasons as $rr)
                                                <option value="{{ $rr->id }}">{{ $rr->label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="text-[10px] font-semibold text-rose-900 block">Detailed Remarks for Super Admin</label>
                                        <textarea wire:model="replacementRemarks" rows="2" placeholder="Call attempts made, customer feedback, reasons..." class="w-full text-xs p-2 rounded-lg border border-rose-200 bg-white text-ink mt-0.5"></textarea>
                                    </div>
                                    <textarea wire:model="replacementSupportingInfo" rows="2" placeholder="Supporting information (optional)" class="w-full text-xs p-2 rounded-lg border border-rose-200 bg-white text-ink"></textarea>
                                    <button type="button" wire:click="requestReplacement"
                                        class="w-full py-2 bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs rounded-lg transition shadow-2xs cursor-pointer">
                                        Request Replacement
                                    </button>
                                </div>
                            @else
                                <div class="p-2.5 rounded bg-white border border-rose-200 text-[11px] text-ink">
                                    A replacement request has been submitted to Lead Panther Super Admin. You will receive credits back into your wallet once approved.
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
                            <span class="text-xs font-bold text-ink uppercase tracking-wider">Lead Activity Trail &amp; Status Changes</span>
                            <button type="button" wire:click="showFullJourney" class="text-xs font-semibold text-primary hover:underline cursor-pointer">Expand Lead Journey &rarr;</button>
                        </div>

                        @foreach($activityLogs as $entry)
                            <div class="p-3 bg-canvas rounded-lg border border-border text-xs space-y-1"><strong>{{ $entry->action }}</strong><p>{{ $entry->user?->name ?? 'System' }} · {{ $entry->created_at }}</p><p class="break-words">Previous: {{ $entry->from_value ?: '—' }}</p><p class="break-words">New: {{ $entry->to_value ?: '—' }}</p></div>
                        @endforeach
                        <!-- Stage History -->
                        <div class="space-y-2">
                            @forelse($statusHistory as $sh)
                                <div class="p-3 bg-canvas rounded-lg border border-border text-xs flex items-start gap-3">
                                    <div class="w-6 h-6 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center shrink-0 mt-0.5">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span class="font-bold text-ink">Stage changed to {{ ucfirst(str_replace('_', ' ', $sh->to_status)) }}</span>
                                            <span class="text-muted text-[10px]">(from {{ ucfirst(str_replace('_', ' ', $sh->from_status)) }})</span>
                                        </div>
                                        <div class="text-[10px] text-muted font-mono mt-0.5">
                                            By {{ $sh->changer?->name ?? 'User #'.$sh->changed_by }} &bull; {{ \Carbon\Carbon::parse($sh->changed_at)->format('d M Y, H:i') }}
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="py-4 text-center text-muted text-xs">No stage history records logged yet.</div>
                            @endforelse

                            <!-- Call Logs -->
                            @foreach($leadCalls as $call)
                                <div class="p-3 bg-canvas rounded-lg border border-border text-xs flex items-start gap-3">
                                    <div class="w-6 h-6 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0 mt-0.5">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="font-bold text-ink">Call Logged: {{ $call->outcome }}</div>
                                        <div class="text-[10px] text-muted font-mono mt-0.5">
                                            Duration: {{ (int)($call->duration_seconds / 60) }} min &bull; {{ \Carbon\Carbon::parse($call->called_at)->format('d M Y, H:i') }}
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
                        <span class="text-xs font-bold text-ink uppercase tracking-wider block">Customer Messages &amp; Dispatches</span>
                        <div class="space-y-2">
                            @forelse($communications as $comm)
                                <div class="p-3 bg-canvas rounded-lg border border-border space-y-1 text-xs">
                                    <div class="flex items-center justify-between">
                                        <span class="font-bold text-ink uppercase text-[10px]">{{ $comm->channel }}</span>
                                        <span class="text-muted font-mono text-[10px]">{{ \Carbon\Carbon::parse($comm->sent_at)->format('M d, H:i') }}</span>
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
</div>
