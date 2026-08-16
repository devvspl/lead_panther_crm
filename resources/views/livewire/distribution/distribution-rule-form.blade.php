<div class="space-y-6 max-w-4xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-ink">Lead Distribution Configuration</h1>
            <p class="text-xs text-muted">Configure lead assignment rules for <span
                    class="font-bold text-ink">{{ $project->name }}</span>.</p>
        </div>
    </div>



    <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-6">
        <!-- Partner / Team Scope Selector -->
        <div>
            <label class="text-xs font-bold text-ink">Rule Target Scope (Channel Partner / Builder Team)</label>
            <livewire:shared.searchable-select :model="\App\Models\ChannelPartner::class" :searchable="true"
                placeholder="Direct Builder In-house Sales Team" wire:model="channelPartnerId" key="dist-cp" />
        </div>

        <!-- Radio Cards for Rule Types -->
        <div class="space-y-3">
            <label class="text-xs font-bold text-ink">Select Distribution Strategy</label>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <!-- Round Robin Card -->
                <div wire:click="$set('ruleType', 'round_robin')"
                    class="p-4 rounded-card border transition cursor-pointer flex flex-col justify-between space-y-2 {{ $ruleType === 'round_robin' ? 'border-ink bg-canvas ring-2 ring-ink' : 'border-border bg-surface hover:border-gray-400' }}">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-ink">Round Robin Rotation</span>
                        <input type="radio" wire:model="ruleType" value="round_robin" class="text-ink">
                    </div>
                    <p class="text-[11px] text-muted">Equal sequential rotation across all eligible sales executives.
                    </p>
                </div>

                <!-- Manual Queue Card -->
                <div wire:click="$set('ruleType', 'manual')"
                    class="p-4 rounded-card border transition cursor-pointer flex flex-col justify-between space-y-2 {{ $ruleType === 'manual' ? 'border-ink bg-canvas ring-2 ring-ink' : 'border-border bg-surface hover:border-gray-400' }}">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-ink">Manual Manager Queue</span>
                        <input type="radio" wire:model="ruleType" value="manual" class="text-ink">
                    </div>
                    <p class="text-[11px] text-muted">Leads sit unassigned until an Account Manager assigns them
                        manually.</p>
                </div>

                <!-- Location-wise Card -->
                <div wire:click="$set('ruleType', 'location')"
                    class="p-4 rounded-card border transition cursor-pointer flex flex-col justify-between space-y-2 {{ $ruleType === 'location' ? 'border-ink bg-canvas ring-2 ring-ink' : 'border-border bg-surface hover:border-gray-400' }}">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-ink">Location-wise Mapping</span>
                        <input type="radio" wire:model="ruleType" value="location" class="text-ink">
                    </div>
                    <p class="text-[11px] text-muted">Assign based on matching lead city or pincode mapping.</p>
                </div>

                <!-- Priority First Refusal Card -->
                <div wire:click="$set('ruleType', 'priority')"
                    class="p-4 rounded-card border transition cursor-pointer flex flex-col justify-between space-y-2 {{ $ruleType === 'priority' ? 'border-ink bg-canvas ring-2 ring-ink' : 'border-border bg-surface hover:border-gray-400' }}">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-ink">Priority First-Refusal</span>
                        <input type="radio" wire:model="ruleType" value="priority" class="text-ink">
                    </div>
                    <p class="text-[11px] text-muted">Offers leads to top-tier channel partners before falling back.</p>
                </div>

                <!-- Availability Card -->
                <div wire:click="$set('ruleType', 'availability')"
                    class="p-4 rounded-card border transition cursor-pointer flex flex-col justify-between space-y-2 {{ $ruleType === 'availability' ? 'border-ink bg-canvas ring-2 ring-ink' : 'border-border bg-surface hover:border-gray-400' }}">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-ink">Real-time Availability</span>
                        <input type="radio" wire:model="ruleType" value="availability" class="text-ink">
                    </div>
                    <p class="text-[11px] text-muted">Only assign leads to executives currently marked online/active.
                    </p>
                </div>

                <!-- Project Direct Team Card -->
                <div wire:click="$set('ruleType', 'project_wise')"
                    class="p-4 rounded-card border transition cursor-pointer flex flex-col justify-between space-y-2 {{ $ruleType === 'project_wise' ? 'border-ink bg-canvas ring-2 ring-ink' : 'border-border bg-surface hover:border-gray-400' }}">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-ink">Project-wise Dedicated Team</span>
                        <input type="radio" wire:model="ruleType" value="project_wise" class="text-ink">
                    </div>
                    <p class="text-[11px] text-muted">Assign exclusively to sales team assigned to this specific
                        project.</p>
                </div>
            </div>
        </div>

        <!-- Conditional Config Section -->
        @if($ruleType === 'location')
            <div class="p-4 bg-canvas rounded-card border border-border space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-bold text-ink">City / Pincode Mapping Table</h3>
                    <x-ui.button wire:click="addLocationRow" variant="secondary" class="text-[10px] px-2 py-1">+ Add City
                        Mapping</x-ui.button>
                </div>

                @foreach($locationRows as $index => $row)
                    <div class="flex items-center space-x-3">
                        <input type="text" wire:model="locationRows.{{ $index }}.city" placeholder="City Name (e.g. Mumbai)"
                            class="w-1/2 h-8 text-xs px-3.5 rounded-lg border border-border bg-canvas text-ink focus:outline-none focus:ring-2 focus:ring-ink transition">
                        <livewire:shared.searchable-select :model="\App\Models\User::class"
                            roleFilter="sales-executive, Sales Executive" :searchable="true" placeholder="Select Executive"
                            wire:model="locationRows.{{ $index }}.user_id" key="dist-user-{{ $index }}" />
                        <button wire:click="removeLocationRow({{ $index }})"
                            class="text-danger text-xs hover:underline">Remove</button>
                    </div>
                @endforeach
            </div>
        @elseif($ruleType === 'priority')
            <div class="p-4 bg-canvas rounded-card border border-border space-y-2">
                <h3 class="text-xs font-bold text-ink">Select Priority Executives (Order of refusal)</h3>
                <div class="grid grid-cols-2 gap-2">
                    @foreach($executives as $e)
                        <label class="flex items-center space-x-2 text-xs text-ink">
                            <input type="checkbox" wire:model="priorityUserIds" value="{{ $e->id }}">
                            <span>{{ $e->name }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="flex items-center justify-between pt-4 border-t border-border">
            <label class="flex items-center space-x-2 text-xs font-bold text-ink">
                <input type="checkbox" wire:model="isActive" class="rounded border-border text-ink">
                <span>Rule Active & Enforced</span>
            </label>

            <x-ui.button wire:click="saveRule" variant="primary" class="text-xs">
                Save Distribution Rule
            </x-ui.button>
        </div>
    </div>
</div>