<div class="space-y-4" wire:poll.5s="$refresh">
    <div class="rounded-card border border-border bg-canvas p-4 space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div><h3 class="text-sm font-bold text-ink">Client Delivery</h3><p class="text-xs text-muted mt-1">{{ $plan ? ucwords(str_replace('_', ' ', $plan->settings['frequency'])).' · '.($plan->settings['quantity_mode'] === 'all' ? 'Send All Available Leads' : $plan->settings['quantity'].' leads per cycle').' · '.ucfirst($plan->state) : 'Configure a destination and schedule to start pushing leads.' }}</p></div>
            <div class="flex flex-wrap gap-2 text-xs">
                <button type="button" wire:click="$set('showConfiguration', true)" class="h-8 px-3 rounded-lg border border-border bg-surface font-semibold">Configure Delivery</button>
                @if($plan)
                    <button type="button" wire:click="runNow" @if($stats['Pending'] > 0) wire:confirm="Start one delivery cycle using the saved limits and working hours?" @endif class="h-8 px-3 rounded-lg bg-ink text-white font-semibold">{{ $stats['Pending'] == 0 && $stats['Eligible'] > 0 ? 'Push Existing Leads' : 'Send Cycle Now' }}</button>
                    @if($plan->state === 'active')<button type="button" wire:click="control('pause')" class="h-8 px-3 rounded-lg border border-border bg-surface">Pause</button>@else<button type="button" wire:click="control('resume')" class="h-8 px-3 rounded-lg border border-border bg-surface">Resume</button>@endif
                    <button type="button" wire:click="control('stop')" wire:confirm="Stop this delivery? Unsent leads will remain in the pending queue." class="h-8 px-3 rounded-lg border border-red-200 text-red-700 bg-surface">Stop</button>
                @endif
            </div>
        </div>
        @if($stats['Eligible'] > 0 && $stats['Pending'] == 0)
            <p class="rounded-lg border border-border bg-surface px-3 py-2 text-xs text-ink">{{ $stats['Eligible'] }} existing lead(s) have not been queued for this delivery plan. Saving settings does not send them. Use Push Existing Leads to confirm the transfer.</p>
        @endif
        <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
            @foreach($stats as $label => $value)<div class="rounded-lg bg-surface border border-border p-3"><div class="text-[10px] uppercase font-semibold text-muted">{{ $label }}</div><div class="mt-1 text-xs font-bold text-ink break-words">{{ $value }}</div></div>@endforeach
        </div>
        @if($run)
            <div class="text-xs text-ink space-y-2" role="status"><div class="flex flex-wrap gap-x-4 gap-y-1"><strong>Batch #{{ $run->id }} · {{ ucfirst($run->status) }}</strong><span>Total: {{ $run->total }}</span><span>Processed: {{ $processed }}</span><span>Successful: {{ $successes }}</span><span>Failed: {{ $failures }}</span><span>Remaining: {{ $remaining }}</span></div><progress class="w-full h-2 accent-primary" max="{{ max(1, $run->total) }}" value="{{ $processed }}"></progress></div>
        @endif
    </div>

    <details class="rounded-card border border-border bg-surface p-4">
        <summary class="text-sm font-semibold text-ink cursor-pointer">Delivery History & Reports</summary>
        <div class="mt-4 space-y-3">
            <div class="grid grid-cols-2 lg:grid-cols-6 gap-3 text-xs">
                <div><label>From</label><x-text-input variant="compact" type="date" wire:model.live="historyFrom" /></div>
                <div><label>To</label><x-text-input variant="compact" type="date" wire:model.live="historyTo" /></div>
                <div><label>Client</label><x-ui.themed-select wire:model.live="historyClient" :options="$historyClients->pluck('name','id')->all()" placeholder="All Clients" :searchable="true" /></div>
                <div><label>Project</label><x-ui.themed-select wire:model.live="historyProject" :options="$historyProjects->pluck('name','id')->all()" placeholder="All Projects" :searchable="true" /></div>
                <div><label>Campaign</label><x-ui.themed-select wire:model.live="historyCampaign" :options="$historyCampaigns->pluck('name','id')->all()" placeholder="All Campaigns" :searchable="true" /></div>
                <div><label>Status</label><x-ui.themed-select wire:model.live="historyStatus" :options="[''=>'All Statuses','pending'=>'Pending','queued'=>'Queued','sent'=>'Sent','failed'=>'Failed','accepted'=>'Accepted']" :searchable="true" /></div>
            </div>
            <x-ui.export-button target="exportHistory" label="Export Delivery History" />
            <x-ui.advanced-table :columns="$this->historyColumns()" :rows="$history" :show-search="false" :show-filter-dropdown="false" :show-configurations="false" :show-skeleton="false" table-name="client-delivery-history" empty-title="No delivery history" empty-message="Queued and completed transfers will appear here." />
            @error('delivery')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
    </details>

    <x-ui.offcanvas wire:model="showConfiguration" name="client-delivery-configuration" title="Push Leads to Client" subtitle="Choose where to send the leads and when." maxWidth="2xl">
        <div class="space-y-5 text-xs">
            @if($selectedLeadIds)<p class="rounded-lg border border-border bg-canvas p-3 font-semibold">{{ count($selectedLeadIds) }} lead(s) selected</p>@endif
            <div class="grid sm:grid-cols-3 gap-3">
                <div><label class="font-semibold">Destination Client *</label><x-ui.themed-select wire:model.live="settings.client_id" :options="$clients->pluck('name','id')->all()" placeholder="Choose client" :searchable="true" /></div>
                <div wire:key="delivery-project-{{ $settings['client_id'] }}"><label class="font-semibold">Project *</label><x-ui.themed-select wire:model.live="settings.project_id" :options="$projects->pluck('name','id')->all()" placeholder="Choose project" :searchable="true" /></div>
                <div wire:key="delivery-campaign-{{ $settings['project_id'] }}"><label class="font-semibold">Campaign (Optional)</label><x-ui.themed-select wire:model.live="settings.campaign_id" :options="['' => 'Use project default'] + $campaigns->pluck('name','id')->all()" placeholder="Use project default" :searchable="true" /></div>
            </div>
            <div class="grid sm:grid-cols-2 gap-3">
                <div><label>Delivery Frequency</label><x-ui.themed-select wire:model.live="settings.frequency" :options="['immediate'=>'Immediately','15_minutes'=>'Every 15 minutes','30_minutes'=>'Every 30 minutes','hourly'=>'Hourly','daily'=>'Daily','weekly'=>'Weekly','manual'=>'Manual Only']" :searchable="true" /></div>
                <div><label>Lead Quantity</label><x-ui.themed-select wire:model.live="settings.quantity_mode" :options="['fixed'=>'Fixed Batch Quantity','all'=>'Send All Available Leads']" /></div>
                @if($settings['quantity_mode'] === 'fixed')<div><label>Leads to Send *</label><x-text-input variant="compact" type="number" min="1" wire:model="settings.quantity" /></div>@endif
                <div class="space-y-2"><label class="flex items-center gap-2 h-8"><input type="checkbox" wire:model.live="settings.daily_enabled">Set a daily limit</label>@if($settings['daily_enabled'])<x-text-input variant="compact" type="number" min="1" wire:model="settings.daily_limit" placeholder="Maximum leads per day" />@endif</div>
                @if(in_array($settings['frequency'], ['daily','weekly']))<div><label>Scheduled Time</label><x-text-input variant="compact" type="time" wire:model="settings.daily_time" /></div>@endif
                @if($settings['frequency'] === 'weekly')<div><label>Weekly Day</label><x-ui.themed-select wire:model="settings.weekly_day" :options="[0=>'Sunday',1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday']" /></div>@endif
            </div>
            <details class="rounded-lg border border-border p-3"><summary class="font-semibold text-ink cursor-pointer">Advanced scheduling (optional)</summary>
                <div class="grid sm:grid-cols-2 gap-3 mt-4">
                    <div><label>Start Date</label><x-text-input variant="compact" type="date" wire:model="settings.start_date" /></div>
                    <div><label>End Date</label><x-text-input variant="compact" type="date" wire:model="settings.end_date" /></div>
                    <div><label>Daily Start Time</label><x-text-input variant="compact" type="time" wire:model="settings.window_start" /></div>
                    <div><label>Daily End Time</label><x-text-input variant="compact" type="time" wire:model="settings.window_end" /></div>
                    <div><label>Timezone</label><x-ui.themed-select wire:model="settings.timezone" :options="array_combine(\DateTimeZone::listIdentifiers(), \DateTimeZone::listIdentifiers())" :searchable="true" /></div>
                    @if($settings['quantity_mode'] === 'fixed')<div><label>Maximum per Cycle (optional)</label><x-text-input variant="compact" type="number" min="1" wire:model="settings.max_cycle" /></div>@endif
                </div>
                <fieldset class="mt-4"><legend class="font-semibold mb-2">Working Days</legend><div class="flex flex-wrap gap-3">@foreach([0=>'Sun',1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat'] as $day => $label)<label class="flex items-center gap-1"><input type="checkbox" wire:model="settings.working_days" value="{{ $day }}">{{ $label }}</label>@endforeach</div></fieldset>
            </details>
            <p class="text-muted">{{ $settings['frequency'] === 'manual' ? 'Push sends one cycle now. New leads wait until you push them.' : 'Delivery runs automatically at the selected frequency.' }} {{ $settings['daily_enabled'] ? 'The daily limit applies.' : 'No daily limit.' }} Existing queues and duplicate protection are preserved.</p>
            @if($errors->any())<div role="alert" class="rounded-lg border border-red-200 p-3 text-red-700">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
            <div class="flex flex-wrap gap-2 border-t border-border pt-4">
                <button type="button" wire:click="saveSettings" @if($settings['quantity_mode'] === 'all' || !$settings['daily_enabled']) wire:confirm="Save these settings? {{ $settings['quantity_mode'] === 'all' ? 'Send All is selected.' : '' }} {{ !$settings['daily_enabled'] ? 'The daily limit is off.' : '' }}" @endif class="px-4 h-9 rounded-lg border border-border font-semibold">Save Settings</button>
                <button type="button" wire:click="pushLeads" wire:confirm="Push the selected leads to this client? Confirm that you have permission to share them. {{ $settings['quantity_mode'] === 'all' ? 'Send All is selected.' : '' }} {{ !$settings['daily_enabled'] ? 'The daily limit is off.' : '' }}" class="px-4 h-9 rounded-lg bg-ink text-white font-semibold">Push Leads</button>
                <button type="button" wire:click="pushLeads(true)" wire:confirm="Queue all eligible leads for this client? Confirm that you have permission to share them. {{ !$settings['daily_enabled'] ? 'The daily limit is off.' : '' }}" class="px-4 h-9 rounded-lg border border-border font-semibold">Queue All Eligible</button>
            </div>
        </div>
    </x-ui.offcanvas>
    <x-ui.offcanvas wire:model="showAttempts" name="delivery-attempt-history" title="Transfer History" subtitle="Actual attempt records are retained across retries and batch changes." maxWidth="xl">
        @if($inspectedItem)
            <div class="space-y-4 text-xs text-ink"><p class="font-bold">{{ $inspectedItem->lead?->lead_code }} · {{ $inspectedItem->lead?->name }}</p><p>{{ $inspectedItem->client?->name }} / {{ $inspectedItem->project?->name }} / {{ $inspectedItem->campaign?->name }}</p><p>Queued {{ $inspectedItem->created_at->format('d M Y H:i T') }} · {{ ucfirst($inspectedItem->status) }}</p>
                @forelse($inspectedItem->attemptHistory as $attempt)<div class="border border-border rounded-lg p-3 space-y-1"><strong>Batch #{{ $attempt->run_id }} · {{ ucfirst($attempt->status) }}</strong><p>{{ $attempt->created_at->format('d M Y H:i T') }}</p>@if($attempt->failure_reason)<p class="text-red-700">{{ $attempt->failure_reason }}</p>@endif</div>@empty<p class="text-muted">No delivery attempts yet. This transfer is waiting in the queue.</p>@endforelse
                @if($inspectedItem->accepted_at)<p>Client acceptance recorded {{ $inspectedItem->accepted_at->format('d M Y H:i T') }}.</p>@endif
            </div>
        @endif
    </x-ui.offcanvas>
</div>
