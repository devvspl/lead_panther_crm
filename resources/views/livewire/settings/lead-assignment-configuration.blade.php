<div class="bg-surface border border-border rounded-card p-5 space-y-4" wire:poll.15s>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div><h2 class="font-bold text-ink">Lead Assignment Configuration</h2><p class="text-xs text-muted">Automatic assignment to this organization's sales executives. Unsent client leads remain hidden.</p></div>
        @if($unassigned !== null)<span class="text-xs text-muted">Unassigned delivered leads: <strong>{{ $unassigned }}</strong></span>@endif
    </div>
    <p class="text-xs text-muted">{{ $effective['enabled'] && $effective['method'] !== 'manual' ? 'Automatic' : 'Manual' }} · {{ ucwords(str_replace('_',' ',$effective['method'])) }} · Daily limit: {{ $effective['daily_limit'] ?: 'Unlimited' }} · {{ $effective['batch_size'] }} per batch · {{ $effective['start_time'] }}–{{ $effective['end_time'] }} {{ $effective['timezone'] }}</p>
    @if($canConfigure)
    <form wire:submit="save" class="space-y-4">
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="settings.enabled"> Enable automatic assignment</label>
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
            <div><label class="block mb-1">Assignment team</label><x-ui.themed-select wire:model="settings.team_id" :options="$teams" placeholder="All organization teams" :searchable="true" class="w-full" /></div>
            <div><label class="block mb-1">Assignment method</label><x-ui.themed-select wire:model="settings.method" :options="['round_robin'=>'Round Robin','equal_distribution'=>'Equal Distribution','priority'=>'Priority Based','manual'=>'Manual Assignment']" class="w-full" /></div>
            <label>Maximum per executive / day (0 = unlimited)<x-text-input variant="compact" type="number" min="0" wire:model="settings.daily_limit" class="mt-1" /></label>
            <label>Leads per assignment batch<x-text-input variant="compact" type="number" min="1" max="1000" wire:model="settings.batch_size" class="mt-1" /></label>
            <div><label class="block mb-1">Timezone</label><x-ui.themed-select wire:model="settings.timezone" :options="array_combine(timezone_identifiers_list(),timezone_identifiers_list())" :searchable="true" class="w-full" /></div>
            <label>Working hours from<x-text-input variant="compact" type="time" wire:model="settings.start_time" class="mt-1" /></label>
            <label>Working hours to<x-text-input variant="compact" type="time" wire:model="settings.end_time" class="mt-1" /></label>
        </div>
        <fieldset class="text-xs"><legend class="font-semibold mb-2">Working days</legend><div class="flex flex-wrap gap-4">@foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $day=>$label)<label class="flex gap-1 items-center"><input type="checkbox" wire:model="settings.working_days" value="{{ $day }}">{{ $label }}</label>@endforeach</div></fieldset>
        <details class="border border-border rounded-lg p-3 text-xs"><summary class="cursor-pointer font-semibold">Project &amp; campaign eligibility (empty = all in this organization)</summary><div class="grid sm:grid-cols-2 gap-4 mt-3"><fieldset><legend class="font-bold mb-2">Projects</legend>@forelse($projects as $id=>$name)<label class="flex gap-2 mb-2"><input type="checkbox" value="{{ $id }}" wire:model="settings.project_ids">{{ $name }}</label>@empty<p class="text-muted">No mapped projects.</p>@endforelse</fieldset><fieldset><legend class="font-bold mb-2">Campaigns</legend>@forelse($campaigns as $id=>$name)<label class="flex gap-2 mb-2"><input type="checkbox" value="{{ $id }}" wire:model="settings.campaign_ids">{{ $name }}</label>@empty<p class="text-muted">No campaigns.</p>@endforelse</fieldset></div></details>
        <p class="text-xs text-muted">Priority 1 is highest. Inactive team memberships are always skipped. Pending leads retry each minute, subject to the saved batch size, working hours and daily capacity.</p>
        <div class="overflow-x-auto border border-border rounded-lg"><table class="w-full text-xs"><thead class="bg-canvas text-muted"><tr><th class="p-3 text-left">Executive</th><th class="p-3 text-left">Available for assignment</th><th class="p-3 text-left">Priority</th></tr></thead><tbody>@forelse($statistics as $row)<tr class="border-t border-border" wire:key="assignment-member-{{ $row['id'] }}"><td class="p-3">{{ $row['name'] }}</td><td class="p-3"><input type="checkbox" wire:model="settings.members.{{ $row['id'] }}.available"></td><td class="p-3"><x-text-input variant="compact" type="number" min="1" wire:model="settings.members.{{ $row['id'] }}.priority" /></td></tr>@empty<tr><td colspan="3" class="p-4 text-muted">Add sales executives to an organization team first.</td></tr>@endforelse</tbody></table></div>
        @if($errors->any())<p class="text-sm text-red-600">{{ $errors->first() }}</p>@endif
        <div class="flex gap-2"><button class="bg-ink text-white rounded-lg px-4 py-2 text-xs" wire:loading.attr="disabled">Save Assignment Settings</button><button type="button" wire:click="assignPending" wire:loading.attr="disabled" class="border border-border rounded-lg px-4 py-2 text-xs">Assign Pending Batch</button></div>
    </form>
    @else<p class="text-xs text-muted">Assignment rules are configured by Super Admin. Use the Leads panel to assign or reassign your authorized team leads.</p>@endif
    @php
        $columns = collect(['name'=>'Executive','team'=>'Team','availability'=>'Availability','assigned'=>'Total Assigned','today'=>'Assigned Today','pending'=>'Pending Work','capacity'=>'Daily Capacity Remaining'])->map(fn($label,$key)=>['key'=>$key,'label'=>$label,'priority'=>1])->values()->all();
        $rows = collect($statistics)->map(fn($row)=>(object) array_merge($row,['availability'=>$row['available'] ? 'Available' : 'Unavailable','capacity'=>$row['remaining'] ?? 'Unlimited']));
    @endphp
    <x-ui.advanced-table :columns="$columns" :rows="$rows" :show-search="false" :show-filter-dropdown="false" :show-configurations="false" :show-skeleton="false" table-name="organization-assignment-statistics" empty-title="No Sales Executives" empty-message="Add sales executives to your organization teams." />
</div>
