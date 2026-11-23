<div>
    @if($isOpen && $lead)
    <div class="fixed inset-0 z-50 overflow-y-auto bg-ink/60 backdrop-blur-xs flex items-center justify-center p-2 sm:p-4"
         wire:keydown.escape="close"
         x-data="{ activeTab: @entangle('activeTab') }">
        <div class="bg-surface rounded-2xl border border-border shadow-2xl max-w-5xl w-full flex flex-col max-h-[94vh] overflow-hidden animate-in fade-in zoom-in-95 duration-150">
            
            <!-- 1. MODAL HEADER -->
            <div class="p-4 sm:p-5 border-b border-border bg-canvas flex flex-col sm:flex-row sm:items-center justify-between gap-3 shrink-0">
                <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="px-2.5 py-0.5 text-xs font-bold font-mono rounded bg-purple-700 text-white flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            Super Admin Master Inspector &amp; Editor
                        </span>
                        <span class="px-2 py-0.5 text-xs font-mono font-bold rounded bg-ink text-white">#{{ $lead->lead_code }}</span>
                        <x-ui.badge status="{{ $lead->current_stage }}">{{ ucfirst(str_replace('_', ' ', $lead->current_stage)) }}</x-ui.badge>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider {{ $lead->status === 'active' ? 'bg-emerald-50 text-emerald-800 border border-emerald-300' : 'bg-canvas text-muted border border-border' }}">
                            {{ $lead->status }}
                        </span>
                    </div>
                    <h2 class="text-lg sm:text-xl font-bold text-ink mt-1 truncate flex items-center gap-2">
                        <span>{{ $lead->name ?: 'Unnamed Lead' }}</span>
                        @if($lead->mobile)
                            <span class="text-xs font-normal text-muted font-mono">({{ $lead->mobile }})</span>
                        @endif
                    </h2>
                    <p class="text-xs text-muted flex items-center gap-2 flex-wrap mt-0.5">
                        <span>Client: <strong class="text-ink">{{ $lead->client?->name ?: 'Unassigned' }}</strong></span>
                        <span>&bull;</span>
                        <span>Project: <strong class="text-ink">{{ $lead->project?->name ?: 'Direct / General' }}</strong></span>
                        <span>&bull;</span>
                        <span>Received: <strong class="text-ink">{{ $lead->created_at?->format('d M Y, h:i A') }}</strong></span>
                    </p>
                </div>
                
                <div class="flex items-center gap-2 shrink-0">
                    <button type="button" wire:click="close" class="text-muted hover:text-ink p-1.5 rounded-lg hover:bg-surface border border-transparent hover:border-border transition cursor-pointer" title="Close Modal">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>

            <!-- 2. TAB NAVIGATION -->
            <div class="bg-surface border-b border-border px-4 sm:px-6 flex items-center gap-1 sm:gap-2 overflow-x-auto shrink-0 scrollbar-none">
                <button type="button" @click="activeTab = 'master'"
                    :class="activeTab === 'master' ? 'border-primary text-primary font-bold' : 'border-transparent text-muted hover:text-ink font-medium'"
                    class="py-3 px-3 text-xs border-b-2 flex items-center gap-1.5 transition whitespace-nowrap cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    <span>Master Fields &amp; Edit</span>
                </button>

                <button type="button" @click="activeTab = 'meta'"
                    :class="activeTab === 'meta' ? 'border-primary text-primary font-bold' : 'border-transparent text-muted hover:text-ink font-medium'"
                    class="py-3 px-3 text-xs border-b-2 flex items-center gap-1.5 transition whitespace-nowrap cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    <span>Meta Form Intelligence</span>
                    @if(!empty($metaFormAnswers))
                        <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800">{{ count($metaFormAnswers) }}</span>
                    @endif
                </button>

                <button type="button" @click="activeTab = 'metadata'"
                    :class="activeTab === 'metadata' ? 'border-primary text-primary font-bold' : 'border-transparent text-muted hover:text-ink font-medium'"
                    class="py-3 px-3 text-xs border-b-2 flex items-center gap-1.5 transition whitespace-nowrap cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2 1.5 3 3.5 3h9c2 0 3.5-1 3.5-3V7c0-2-1.5-3-3.5-3h-9C5.5 4 4 5 4 7zm0 5h16M9 4v16"/></svg>
                    <span>All Metadata &amp; Internals</span>
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-canvas text-muted border border-border">{{ $metadataList->count() }}</span>
                </button>

                <button type="button" @click="activeTab = 'history'"
                    :class="activeTab === 'history' ? 'border-primary text-primary font-bold' : 'border-transparent text-muted hover:text-ink font-medium'"
                    class="py-3 px-3 text-xs border-b-2 flex items-center gap-1.5 transition whitespace-nowrap cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>Timeline &amp; Audit Logs</span>
                </button>
            </div>

            <!-- 3. TAB BODIES (SCROLLABLE) -->
            <div class="p-4 sm:p-6 overflow-y-auto flex-1 space-y-6 text-xs bg-canvas/40">

                <!-- TAB 1: MASTER EDIT & FIELDS -->
                <div x-show="activeTab === 'master'" class="space-y-6">
                    <form wire:submit="saveLead" id="super-admin-lead-form" class="space-y-6">

                        <!-- SECTION A: Contact & Customer Details -->
                        <div class="p-4 sm:p-5 rounded-xl bg-surface border border-border shadow-2xs space-y-4">
                            <div class="flex items-center justify-between pb-2 border-b border-border">
                                <h3 class="text-xs font-bold text-ink uppercase tracking-wider flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-primary"></span>
                                    1. Contact &amp; Personal Information
                                </h3>
                                <span class="text-[11px] text-muted">ID: {{ $lead->id }}</span>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-ink mb-1.5">Customer Name <span class="text-rose-500">*</span></label>
                                    <input type="text" wire:model="name" class="w-full h-9 rounded-lg border border-border bg-canvas px-3 text-xs focus:ring-1 focus:ring-primary focus:border-primary" required />
                                    @error('name') <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-ink mb-1.5">Mobile Phone <span class="text-rose-500">*</span></label>
                                    <div class="flex items-center gap-1.5">
                                        <input type="text" wire:model="mobile" class="w-full h-9 rounded-lg border border-border bg-canvas px-3 text-xs font-mono focus:ring-1 focus:ring-primary focus:border-primary" required />
                                        @if($mobile)
                                            <a href="tel:{{ $mobile }}" class="p-2 h-9 rounded-lg border border-border bg-surface hover:bg-canvas text-primary shrink-0 flex items-center justify-center" title="Call">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                            </a>
                                            <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $mobile) }}" target="_blank" class="p-2 h-9 rounded-lg border border-border bg-surface hover:bg-emerald-50 text-emerald-600 shrink-0 flex items-center justify-center" title="WhatsApp">
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                                            </a>
                                        @endif
                                    </div>
                                    @error('mobile') <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-ink mb-1.5">Email Address</label>
                                    <input type="email" wire:model="email" class="w-full h-9 rounded-lg border border-border bg-canvas px-3 text-xs focus:ring-1 focus:ring-primary focus:border-primary" />
                                    @error('email') <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-ink mb-1.5">City</label>
                                    <input type="text" wire:model="city" placeholder="e.g. Gurgaon, Noida, Mumbai" class="w-full h-9 rounded-lg border border-border bg-canvas px-3 text-xs focus:ring-1 focus:ring-primary focus:border-primary" />
                                    @error('city') <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-ink mb-1.5">Specific Location / Sector</label>
                                    <input type="text" wire:model="location" placeholder="e.g. Sector 106, Dwarka Expressway" class="w-full h-9 rounded-lg border border-border bg-canvas px-3 text-xs focus:ring-1 focus:ring-primary focus:border-primary" />
                                    @error('location') <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </div>

                        <!-- SECTION B: Property Requirement & Budget -->
                        <div class="p-4 sm:p-5 rounded-xl bg-surface border border-border shadow-2xs space-y-4">
                            <div class="flex items-center justify-between pb-2 border-b border-border">
                                <h3 class="text-xs font-bold text-ink uppercase tracking-wider flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                    2. Property Intent, Configuration &amp; Budget
                                </h3>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-ink mb-1.5">Property Type / Unit Config</label>
                                    <input type="text" wire:model="property_type" placeholder="e.g. 2 BHK, 3 BHK Luxury, Studio, Villa, Commercial" class="w-full h-9 rounded-lg border border-border bg-canvas px-3 text-xs focus:ring-1 focus:ring-primary focus:border-primary" />
                                    @error('property_type') <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-ink mb-1.5">
                                        Budget (INR Value)
                                        @if($budget)
                                            <span class="text-primary font-bold ml-1 font-mono">({{ \App\Support\LeadPropertyAndBudgetExtractor::formatShortBudget($budget) }})</span>
                                        @endif
                                    </label>
                                    <input type="number" step="any" wire:model.live="budget" placeholder="e.g. 8500000 or 15000000" class="w-full h-9 rounded-lg border border-border bg-canvas px-3 text-xs font-mono focus:ring-1 focus:ring-primary focus:border-primary" />
                                    @error('budget') <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p> @enderror
                                </div>

                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-semibold text-ink mb-1.5">Requirements &amp; Inquiry Notes</label>
                                    <textarea wire:model="requirement" rows="3" placeholder="Enter customer requirements, portal remarks, or inquiry details..." class="w-full rounded-lg border border-border bg-canvas p-3 text-xs focus:ring-1 focus:ring-primary focus:border-primary"></textarea>
                                    @error('requirement') <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </div>

                        <!-- SECTION C: Routing, Client & Project Architecture -->
                        <div class="p-4 sm:p-5 rounded-xl bg-surface border border-border shadow-2xs space-y-4">
                            <div class="flex items-center justify-between pb-2 border-b border-border">
                                <h3 class="text-xs font-bold text-ink uppercase tracking-wider flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                                    3. Client Routing, Project &amp; Assignment
                                </h3>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-ink mb-1.5">Assigned Client / Builder</label>
                                    <select wire:model="client_id" class="w-full h-9 rounded-lg border border-border bg-canvas px-3 text-xs focus:ring-1 focus:ring-primary focus:border-primary">
                                        <option value="">-- No Client Assigned --</option>
                                        @foreach($clients as $c)
                                            <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->email }})</option>
                                        @endforeach
                                    </select>
                                    @error('client_id') <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-ink mb-1.5">CRM Project</label>
                                    <select wire:model.live="project_id" class="w-full h-9 rounded-lg border border-border bg-canvas px-3 text-xs focus:ring-1 focus:ring-primary focus:border-primary">
                                        <option value="">-- No Project Selected --</option>
                                        @foreach($projects as $p)
                                            <option value="{{ $p->id }}">{{ $p->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('project_id') <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-ink mb-1.5">Marketing Campaign</label>
                                    <select wire:model.live="campaign_id" class="w-full h-9 rounded-lg border border-border bg-canvas px-3 text-xs focus:ring-1 focus:ring-primary focus:border-primary">
                                        <option value="">-- Select or Auto-create Campaign --</option>
                                        @foreach($campaigns as $camp)
                                            <option value="{{ $camp->id }}">{{ $camp->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('campaign_id') <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-ink mb-1.5">Lead Ingestion Source</label>
                                    <select wire:model="lead_source_id" class="w-full h-9 rounded-lg border border-border bg-canvas px-3 text-xs focus:ring-1 focus:ring-primary focus:border-primary">
                                        <option value="">-- Select Source --</option>
                                        @foreach($leadSources as $src)
                                            <option value="{{ $src->id }}">{{ $src->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('lead_source_id') <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p> @enderror
                                </div>

                                <div class="sm:col-span-2">
                                    <div class="flex items-center justify-between mb-1.5">
                                        <label class="block text-xs font-semibold text-ink">Assigned Sales Executive</label>
                                        @if($assigned_to)
                                            <button type="button" wire:click="unassignExecutive" class="text-[11px] font-semibold text-amber-600 hover:text-amber-800 transition cursor-pointer">
                                                Remove / Unassign Executive
                                            </button>
                                        @endif
                                    </div>
                                    <select wire:model="assigned_to" class="w-full h-9 rounded-lg border border-border bg-canvas px-3 text-xs focus:ring-1 focus:ring-primary focus:border-primary">
                                        <option value="">-- Unassigned (Not Assigned to Any Executive) --</option>
                                        @foreach($executives as $ex)
                                            <option value="{{ $ex->id }}">{{ $ex->name }} ({{ $ex->email }})</option>
                                        @endforeach
                                    </select>
                                    @error('assigned_to') <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </div>

                        <!-- SECTION D: Pipeline Stage, Status, Scoring & Credit Cost -->
                        <div class="p-4 sm:p-5 rounded-xl bg-surface border border-border shadow-2xs space-y-4">
                            <div class="flex items-center justify-between pb-2 border-b border-border">
                                <h3 class="text-xs font-bold text-ink uppercase tracking-wider flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                                    4. Pipeline Stage, Status &amp; Scoring
                                </h3>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-ink mb-1.5">Current Stage <span class="text-rose-500">*</span></label>
                                    <select wire:model="current_stage" class="w-full h-9 rounded-lg border border-border bg-canvas px-3 text-xs font-semibold focus:ring-1 focus:ring-primary focus:border-primary" required>
                                        @foreach($stages as $key => $lbl)
                                            <option value="{{ $key }}">{{ $lbl }}</option>
                                        @endforeach
                                    </select>
                                    @error('current_stage') <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-ink mb-1.5">Lead Status <span class="text-rose-500">*</span></label>
                                    <select wire:model="status" class="w-full h-9 rounded-lg border border-border bg-canvas px-3 text-xs font-semibold focus:ring-1 focus:ring-primary focus:border-primary" required>
                                        @foreach($statuses as $stKey => $stLbl)
                                            <option value="{{ $stKey }}">{{ $stLbl }}</option>
                                        @endforeach
                                    </select>
                                    @error('status') <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-ink mb-1.5">Lead Score (0 - 100)</label>
                                    <input type="number" min="0" max="100" wire:model="lead_score" class="w-full h-9 rounded-lg border border-border bg-canvas px-3 text-xs font-mono focus:ring-1 focus:ring-primary focus:border-primary" />
                                    @error('lead_score') <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-ink mb-1.5">Lead Cost (Credits)</label>
                                    <input type="number" step="any" min="0" wire:model="lead_cost" placeholder="e.g. 1.00" class="w-full h-9 rounded-lg border border-border bg-canvas px-3 text-xs font-mono focus:ring-1 focus:ring-primary focus:border-primary" />
                                    @error('lead_cost') <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-ink mb-1.5">Booking Status</label>
                                    <input type="text" wire:model="booking_status" placeholder="e.g. pending, confirmed, cancelled" class="w-full h-9 rounded-lg border border-border bg-canvas px-3 text-xs focus:ring-1 focus:ring-primary focus:border-primary" />
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-ink mb-1.5">Replacement Status</label>
                                    <input type="text" wire:model="replacement_status" placeholder="e.g. none, pending, approved, rejected" class="w-full h-9 rounded-lg border border-border bg-canvas px-3 text-xs focus:ring-1 focus:ring-primary focus:border-primary" />
                                </div>
                            </div>
                        </div>

                        <!-- SECTION E: System & Delivery Flags -->
                        <div class="p-4 sm:p-5 rounded-xl bg-surface border border-border shadow-2xs space-y-4">
                            <div class="flex items-center justify-between pb-2 border-b border-border">
                                <h3 class="text-xs font-bold text-ink uppercase tracking-wider flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-neutral-400"></span>
                                    5. Delivery &amp; System Execution Flags
                                </h3>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <label class="flex items-start gap-2.5 p-3 rounded-lg border border-border bg-canvas/60 hover:bg-canvas cursor-pointer">
                                    <input type="checkbox" wire:model="delivery_pending" class="rounded border-border text-primary focus:ring-primary mt-0.5" />
                                    <div>
                                        <span class="font-semibold text-ink block">Delivery Pending</span>
                                        <span class="text-[11px] text-muted block">Awaiting client distribution slot.</span>
                                    </div>
                                </label>

                                <label class="flex items-start gap-2.5 p-3 rounded-lg border border-border bg-canvas/60 hover:bg-canvas cursor-pointer">
                                    <input type="checkbox" wire:model="internal_processing_pending" class="rounded border-border text-primary focus:ring-primary mt-0.5" />
                                    <div>
                                        <span class="font-semibold text-ink block">Internal Processing Pending</span>
                                        <span class="text-[11px] text-muted block">Under manual Super Admin review before release.</span>
                                    </div>
                                </label>

                                <label class="flex items-start gap-2.5 p-3 rounded-lg border border-border bg-canvas/60 hover:bg-canvas cursor-pointer">
                                    <input type="checkbox" wire:model="archive_assignment_hold" class="rounded border-border text-primary focus:ring-primary mt-0.5" />
                                    <div>
                                        <span class="font-semibold text-ink block">Assignment Hold</span>
                                        <span class="text-[11px] text-muted block">Prevents automated re-assignment.</span>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- TAB 2: META ADS & INGESTION DATA -->
                <div x-show="activeTab === 'meta'" class="space-y-6">

                    <!-- Meta Attribution Card -->
                    <div class="p-4 sm:p-5 rounded-xl bg-surface border border-border shadow-2xs space-y-4">
                        <div class="flex items-center justify-between pb-2 border-b border-border">
                            <h3 class="text-xs font-bold text-ink uppercase tracking-wider flex items-center gap-2">
                                <svg class="w-4 h-4 text-blue-600" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                                Meta Campaign, Form &amp; Creative Attribution
                            </h3>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            <div class="p-3 rounded-lg border border-border bg-canvas">
                                <span class="text-[10px] text-muted uppercase font-bold block">Meta Form</span>
                                <span class="font-semibold text-ink block truncate">{{ $metaContext['form_name'] ?: ($metaContext['form_id'] ?: 'N/A') }}</span>
                                @if($metaContext['form_id'])
                                    <span class="text-[10px] text-muted font-mono block">ID: {{ $metaContext['form_id'] }}</span>
                                @endif
                            </div>

                            <div class="p-3 rounded-lg border border-border bg-canvas">
                                <span class="text-[10px] text-muted uppercase font-bold block">Ad Creative</span>
                                <span class="font-semibold text-ink block truncate">{{ $metaContext['ad_name'] ?: ($metaContext['ad_id'] ?: 'N/A') }}</span>
                                @if($metaContext['ad_id'])
                                    <span class="text-[10px] text-muted font-mono block">ID: {{ $metaContext['ad_id'] }}</span>
                                @endif
                            </div>

                            <div class="p-3 rounded-lg border border-border bg-canvas">
                                <span class="text-[10px] text-muted uppercase font-bold block">Meta Campaign</span>
                                <span class="font-semibold text-ink block truncate">{{ $metaContext['campaign_name'] ?: ($metaContext['campaign_id'] ?: 'N/A') }}</span>
                                @if($metaContext['campaign_id'])
                                    <span class="text-[10px] text-muted font-mono block">ID: {{ $metaContext['campaign_id'] }}</span>
                                @endif
                            </div>

                            <div class="p-3 rounded-lg border border-border bg-canvas">
                                <span class="text-[10px] text-muted uppercase font-bold block">Meta Page ID</span>
                                <span class="font-mono text-ink block">{{ $metaContext['page_id'] ?: 'N/A' }}</span>
                            </div>

                            <div class="p-3 rounded-lg border border-border bg-canvas">
                                <span class="text-[10px] text-muted uppercase font-bold block">Meta Leadgen ID</span>
                                <span class="font-mono text-ink block">{{ $metaContext['lead_id'] ?: 'N/A' }}</span>
                            </div>

                            <div class="p-3 rounded-lg border border-border bg-canvas">
                                <span class="text-[10px] text-muted uppercase font-bold block">UTM Source / Medium</span>
                                <span class="font-mono text-ink block">{{ $metaContext['utm_source'] ?: '-' }} / {{ $metaContext['utm_medium'] ?: '-' }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Question & Answers Table -->
                    <div class="p-4 sm:p-5 rounded-xl bg-surface border border-border shadow-2xs space-y-4">
                        <div class="flex items-center justify-between pb-2 border-b border-border">
                            <div>
                                <h3 class="text-xs font-bold text-ink uppercase tracking-wider">
                                    Meta Lead Form Questions &amp; Submitted Answers
                                </h3>
                                <p class="text-[11px] text-muted mt-0.5">Exact questions presented on Facebook/Instagram and responses answered by this customer.</p>
                            </div>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-50 text-blue-800 border border-blue-200">
                                {{ count($metaFormAnswers) }} Questions
                            </span>
                        </div>

                        @if(empty($metaFormAnswers))
                            <div class="py-8 text-center bg-canvas rounded-lg border border-dashed border-border text-muted">
                                <svg class="w-8 h-8 mx-auto text-muted mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                <p class="text-xs font-semibold">No direct meta form answers stored in metadata for this lead.</p>
                                <p class="text-[11px] mt-1">This lead may have been created directly via manual entry or bulk upload.</p>
                            </div>
                        @else
                            <div class="space-y-2.5">
                                @foreach($metaFormAnswers as $question => $answer)
                                    <div class="p-3 rounded-lg border border-border bg-canvas flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                        <div class="min-w-0 sm:w-1/2">
                                            <span class="text-[10px] font-bold uppercase tracking-wider text-muted block">Question / Field</span>
                                            <span class="text-xs font-semibold text-ink block break-words">
                                                {{ is_string($question) ? str_replace('_', ' ', ucwords($question, '_')) : $question }}
                                            </span>
                                        </div>
                                        <div class="min-w-0 sm:w-1/2 bg-surface p-2 rounded border border-border">
                                            <span class="text-[10px] font-bold uppercase tracking-wider text-muted block">Customer Response</span>
                                            <span class="text-xs font-bold text-primary block break-words">
                                                {{ is_array($answer) ? json_encode($answer) : (string) $answer }}
                                            </span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <!-- Raw JSON Payload with Copy Button -->
                    @if(!empty($rawJsonPretty))
                    <div class="p-4 sm:p-5 rounded-xl bg-surface border border-border shadow-2xs space-y-3"
                         x-data="{ copied: false }">
                        <div class="flex items-center justify-between pb-2 border-b border-border">
                            <h3 class="text-xs font-bold text-ink uppercase tracking-wider flex items-center gap-2">
                                <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                                Raw Ingestion Payload (JSON)
                            </h3>
                            <button type="button"
                                @click="navigator.clipboard.writeText($refs.rawJsonBox.innerText); copied = true; setTimeout(() => copied = false, 2000)"
                                class="px-2.5 py-1 text-xs font-semibold rounded-md border border-border bg-canvas hover:bg-surface text-ink transition flex items-center gap-1.5 cursor-pointer">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
                                <span x-text="copied ? 'Copied!' : 'Copy JSON'"></span>
                            </button>
                        </div>

                        <div class="relative">
                            <pre x-ref="rawJsonBox" class="p-3 bg-neutral-900 text-neutral-100 rounded-lg text-[11px] font-mono overflow-x-auto max-h-64 scrollbar-thin leading-relaxed">{{ $rawJsonPretty }}</pre>
                        </div>
                    </div>
                    @endif
                </div>

                <!-- TAB 3: ALL METADATA & INTERNALS -->
                <div x-show="activeTab === 'metadata'" class="space-y-6">

                    <!-- Add New Metadata Form -->
                    <div class="p-4 sm:p-5 rounded-xl bg-surface border border-border shadow-2xs space-y-3">
                        <h3 class="text-xs font-bold text-ink uppercase tracking-wider flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-purple-500"></span>
                            Add / Update Custom Metadata Key
                        </h3>
                        <div class="flex flex-col sm:flex-row sm:items-end gap-3">
                            <div class="flex-1 min-w-0">
                                <label class="block text-xs font-semibold text-ink mb-1">Key Name</label>
                                <input type="text" wire:model="newMetaKey" placeholder="e.g. external_crm_id, preferred_floor, client_feedback" class="w-full h-9 rounded-lg border border-border bg-canvas px-3 text-xs font-mono" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <label class="block text-xs font-semibold text-ink mb-1">Value</label>
                                <input type="text" wire:model="newMetaValue" placeholder="Enter string, ID, or JSON value" class="w-full h-9 rounded-lg border border-border bg-canvas px-3 text-xs" />
                            </div>
                            <button type="button" wire:click="addMetadataKey" class="h-9 px-4 rounded-lg bg-ink text-white font-semibold text-xs hover:bg-neutral-800 transition cursor-pointer shrink-0">
                                Save Key
                            </button>
                        </div>
                        @error('newMetaKey') <p class="text-[11px] text-rose-500">{{ $message }}</p> @enderror
                        @error('newMetaValue') <p class="text-[11px] text-rose-500">{{ $message }}</p> @enderror
                    </div>

                    <!-- Metadata Table -->
                    <div class="p-4 sm:p-5 rounded-xl bg-surface border border-border shadow-2xs space-y-3">
                        <div class="flex items-center justify-between pb-2 border-b border-border">
                            <h3 class="text-xs font-bold text-ink uppercase tracking-wider">
                                Stored Lead Metadata ({{ $metadataList->count() }} Entries)
                            </h3>
                        </div>

                        @if($metadataList->isEmpty())
                            <p class="text-xs text-muted py-4 text-center">No metadata key-values currently stored for this lead.</p>
                        @else
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-xs divide-y divide-border">
                                    <thead class="bg-canvas text-muted text-[10px] font-bold uppercase tracking-wider">
                                        <tr>
                                            <th class="p-2.5">Key</th>
                                            <th class="p-2.5">Value</th>
                                            <th class="p-2.5">Updated</th>
                                            <th class="p-2.5 text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border">
                                        @foreach($metadataList as $meta)
                                            <tr class="hover:bg-canvas/50 transition">
                                                <td class="p-2.5 font-mono font-bold text-ink align-top whitespace-nowrap">{{ $meta->key }}</td>
                                                <td class="p-2.5 text-muted align-top break-words max-w-md font-mono text-[11px]">
                                                    {{ Str::limit($meta->value, 200) }}
                                                </td>
                                                <td class="p-2.5 text-muted align-top whitespace-nowrap text-[11px]">{{ $meta->updated_at?->diffForHumans() }}</td>
                                                <td class="p-2.5 text-right align-top whitespace-nowrap">
                                                    <button type="button" wire:click="deleteMetadataKey({{ $meta->id }})" wire:confirm="Delete this metadata key?" class="text-rose-500 hover:text-rose-700 p-1 rounded hover:bg-rose-50 transition cursor-pointer" title="Delete Key">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- TAB 4: TIMELINE & AUDIT LOGS -->
                <div x-show="activeTab === 'history'" class="space-y-6">

                    <!-- SLA & Timestamps Card -->
                    <div class="p-4 sm:p-5 rounded-xl bg-surface border border-border shadow-2xs space-y-3">
                        <h3 class="text-xs font-bold text-ink uppercase tracking-wider">
                            Lifecycle &amp; SLA Milestones
                        </h3>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                            <div class="p-3 rounded-lg border border-border bg-canvas">
                                <span class="text-[10px] text-muted uppercase font-bold block">Created</span>
                                <span class="font-bold text-ink">{{ $lead->created_at?->format('d M Y, h:i A') }}</span>
                            </div>
                            <div class="p-3 rounded-lg border border-border bg-canvas">
                                <span class="text-[10px] text-muted uppercase font-bold block">Assigned Executive At</span>
                                <span class="font-bold text-ink">{{ $lead->executive_assigned_at ? $lead->executive_assigned_at->format('d M Y, h:i A') : 'Not Assigned' }}</span>
                            </div>
                            <div class="p-3 rounded-lg border border-border bg-canvas">
                                <span class="text-[10px] text-muted uppercase font-bold block">First Response At</span>
                                <span class="font-bold text-ink">{{ $lead->executive_first_response_at ? $lead->executive_first_response_at->format('d M Y, h:i A') : ($lead->first_response_at ? $lead->first_response_at->format('d M Y, h:i A') : 'Pending') }}</span>
                            </div>
                            <div class="p-3 rounded-lg border border-border bg-canvas">
                                <span class="text-[10px] text-muted uppercase font-bold block">Delivered At</span>
                                <span class="font-bold text-ink">{{ $lead->delivered_at ? $lead->delivered_at->format('d M Y, h:i A') : 'Not Delivered' }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Status History -->
                    <div class="p-4 sm:p-5 rounded-xl bg-surface border border-border shadow-2xs space-y-3">
                        <h3 class="text-xs font-bold text-ink uppercase tracking-wider">
                            Stage &amp; Status Transitions ({{ $historyList->count() }})
                        </h3>
                        @if($historyList->isEmpty())
                            <p class="text-xs text-muted py-4 text-center">No stage transition logs recorded for this lead.</p>
                        @else
                            <div class="space-y-2">
                                @foreach($historyList as $hist)
                                    <div class="p-3 rounded-lg border border-border bg-canvas flex items-center justify-between text-xs">
                                        <div class="flex items-center gap-2">
                                            <span class="text-muted text-[11px] font-mono">{{ $hist->from_status }}</span>
                                            <span class="text-muted">&rarr;</span>
                                            <x-ui.badge status="{{ $hist->to_status }}">{{ ucfirst(str_replace('_', ' ', $hist->to_status)) }}</x-ui.badge>
                                        </div>
                                        <div class="text-right text-[11px] text-muted">
                                            <span>{{ $hist->changer?->name ?: 'System' }}</span>
                                            <span class="block">{{ $hist->changed_at ? \Carbon\Carbon::parse($hist->changed_at)->format('d M Y, h:i A') : $hist->created_at?->format('d M Y, h:i A') }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <!-- Assignment History -->
                    <div class="p-4 sm:p-5 rounded-xl bg-surface border border-border shadow-2xs space-y-3">
                        <h3 class="text-xs font-bold text-ink uppercase tracking-wider">
                            Executive Assignment History ({{ $assignmentList->count() }})
                        </h3>
                        @if($assignmentList->isEmpty())
                            <p class="text-xs text-muted py-4 text-center">No assignment logs recorded for this lead.</p>
                        @else
                            <div class="space-y-2">
                                @foreach($assignmentList as $asgn)
                                    <div class="p-3 rounded-lg border border-border bg-canvas flex items-center justify-between text-xs">
                                        <div>
                                            <span class="font-bold text-ink">Assigned To: {{ $asgn->assignedTo?->name ?: 'Unknown' }}</span>
                                            <span class="text-muted block text-[11px]">By: {{ $asgn->assignedBy?->name ?: 'System' }} (Source: {{ $asgn->assignment_source ?: 'auto' }})</span>
                                        </div>
                                        <div class="text-right text-[11px] text-muted">
                                            {{ $asgn->assigned_at ? \Carbon\Carbon::parse($asgn->assigned_at)->format('d M Y, h:i A') : $asgn->created_at?->format('d M Y, h:i A') }}
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

            </div>

            <!-- 4. MODAL FOOTER -->
            <div class="p-4 border-t border-border bg-canvas flex items-center justify-between gap-3 shrink-0">
                <div class="flex items-center gap-2">
                    <button type="button" wire:click="close" class="px-4 py-2 rounded-lg border border-border bg-surface hover:bg-canvas text-xs font-semibold text-ink transition cursor-pointer">
                        Cancel
                    </button>
                </div>

                <div class="flex items-center gap-2">
                    <button type="submit" form="super-admin-lead-form" wire:loading.attr="disabled"
                        class="px-5 py-2 rounded-lg bg-purple-700 hover:bg-purple-800 text-white text-xs font-bold transition shadow-xs flex items-center gap-1.5 cursor-pointer disabled:opacity-50">
                        <svg wire:loading wire:target="saveLead" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>Save Lead Changes</span>
                    </button>
                </div>
            </div>

        </div>
    </div>
    @endif
</div>
