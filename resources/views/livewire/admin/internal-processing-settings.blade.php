<section class="rounded-2xl border border-border bg-surface p-5 space-y-5">
    <div><h3 class="text-sm font-bold text-ink">Lead Panther Internal Processing</h3><p class="text-xs text-muted mt-1">Route new leads to Lead Panther for calling and verification before client delivery. Existing internal leads keep their processing history when this is disabled.</p></div>
    <form wire:submit="saveSettings" class="space-y-4">
        <label class="flex items-center gap-3 text-sm font-semibold text-ink"><span class="relative inline-flex"><input type="checkbox" role="switch" wire:model.live="enabled" class="peer sr-only"><span class="h-6 w-11 rounded-full bg-muted/30 peer-checked:bg-primary peer-focus-visible:ring-2 peer-focus-visible:ring-primary"></span><span class="absolute left-1 top-1 h-4 w-4 rounded-full bg-white transition-transform peer-checked:translate-x-5"></span></span>Enable Internal Lead Processing</label>
        @if($enabled)
            <div class="grid sm:grid-cols-2 gap-4">
                <div><label class="block text-xs font-semibold text-ink mb-1">Internal processing team</label>
                    <livewire:shared.searchable-select wire:model.live="teamId" :options="$teams->pluck('name', 'id')->all()" placeholder="Select platform team" class="block w-full" :key="'internal-team-'.$organizationId" />
                    <p class="text-xs text-muted mt-1">Teams owned by a platform Super Admin or their organization.</p>
                </div>
                <div><label class="block text-xs font-semibold text-ink mb-1">Assignment rule</label><livewire:shared.searchable-select wire:model.live="assignmentMethod" :options="['round_robin' => 'Round robin', 'capacity' => 'Least active internal leads', 'manual' => 'Manual assignment']" placeholder="Select rule" clear-value="" class="block w-full" :key="'internal-assignment-rule-'.$organizationId" /></div>
            </div>
            <fieldset class="border border-border rounded-lg p-3"><legend class="px-1 text-xs font-semibold text-ink">Responsible internal members</legend><div class="grid sm:grid-cols-2 gap-2 text-xs text-ink">@forelse($members as $member)<label class="flex gap-2 items-center"><input type="checkbox" wire:model="memberIds" value="{{ $member->id }}">{{ $member->name }} ({{ $member->email }})</label>@empty<p class="text-muted">Select a platform team with active internal sales executives.</p>@endforelse</div></fieldset>
            <div class="grid sm:grid-cols-2 gap-4">
                <fieldset class="border border-border rounded-lg p-3"><legend class="px-1 text-xs font-semibold text-ink">Available processing statuses</legend><div class="grid gap-2 text-xs text-ink">@foreach($statuses->where('is_system', false) as $status)<label class="flex gap-2"><input type="checkbox" wire:model="statusCodes" value="{{ $status->code }}">{{ $status->label }}</label>@endforeach</div><p class="text-xs text-muted mt-2">Receipt, assignment and handover states are tracked automatically.</p></fieldset>
                <fieldset class="border border-border rounded-lg p-3"><legend class="px-1 text-xs font-semibold text-ink">Forwarding-eligible statuses</legend><div class="grid gap-2 text-xs text-ink">@foreach($statuses->where('can_forward', true) as $status)<label class="flex gap-2"><input type="checkbox" wire:model="forwardStatuses" value="{{ $status->code }}">{{ $status->label }}</label>@endforeach</div><p class="text-xs text-muted mt-2">A connected call and successful verification are also required.</p></fieldset>
            </div>
            <label class="flex gap-2 text-xs text-ink"><input type="checkbox" wire:model="autoForward">Automatically forward after successful verification in an eligible status</label>
        @endif
        @if($errors->any())<p class="text-xs text-red-600">{{ $errors->first() }}</p>@endif
        <button type="submit" wire:loading.attr="disabled" class="rounded-lg bg-primary text-white text-xs font-semibold px-4 py-2">Save Processing Settings</button>
    </form>
    <div class="border-t border-border pt-4 space-y-3">
        <div class="flex flex-wrap items-end gap-3"><h4 class="text-sm font-semibold text-ink mr-auto">Processing Statistics</h4><div><label class="block text-xs text-muted mb-1">Received date range</label><x-ui.date-range-picker wire:model.live="dateRange" wire:custom-from="dateFrom" wire:custom-to="dateTo" placeholder="All Time" align="right" /></div></div>
        <p class="text-xs text-muted">Current state of leads received in this date range. Total received also includes direct-delivery leads.</p>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">@foreach($stats as $label => $count)<div class="border border-border bg-canvas rounded-lg p-3"><div class="text-xs text-muted">{{ $label }}</div><strong class="block text-lg text-ink mt-1">{{ number_format($count) }}</strong></div>@endforeach</div>
    </div>
</section>
