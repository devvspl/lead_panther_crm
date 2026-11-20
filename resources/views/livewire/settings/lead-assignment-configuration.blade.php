<div class="bg-surface border border-border rounded-card p-5 space-y-4" wire:poll.15s>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div><h2 class="font-bold text-ink inline-flex items-center gap-2"><x-ui.team-icon name="settings" class="text-primary" />Lead Assignment Configuration</h2><p class="text-xs text-muted">Assignment rules and executive capacity.</p></div>
        @if($unassigned !== null)<span class="text-xs text-muted">Unassigned delivered leads: <strong>{{ $unassigned }}</strong></span>@endif
    </div>
    <p class="text-xs text-muted">{{ $effective['enabled'] && $effective['method'] !== 'manual' ? 'Automatic' : \App\Support\ClientFacingText::label('Manual') }} · {{ \App\Support\ClientFacingText::label(ucwords(str_replace('_',' ',$effective['method']))) }} · Daily limit: {{ $effective['daily_limit'] ?: 'Unlimited' }} · {{ $effective['batch_size'] }} per batch · {{ $effective['start_time'] }}–{{ $effective['end_time'] }} {{ $effective['timezone'] }}</p>
    @if($canConfigure)
    <form wire:submit="save" class="space-y-4">
        <x-ui.team-switch wire:model="settings.enabled" label="Automatic assignment" />
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
            @unless($isTeamLeader)<div><label class="block mb-1">Assignment team</label><x-ui.themed-select wire:model="settings.team_id" :options="$teams" placeholder="All organization teams" :searchable="true" class="w-full" /></div>@endunless
            <div><label class="block mb-1">Assignment method</label><x-ui.themed-select wire:model="settings.method" :options="$isTeamLeader ? array_merge(['round_robin'=>'Round Robin','manual'=>\App\Support\ClientFacingText::label('Manual')], in_array($settings['method'] ?? '', ['equal_distribution','priority']) ? [$settings['method']=>ucwords(str_replace('_',' ',$settings['method']))] : []) : ['round_robin'=>'Round Robin','equal_distribution'=>'Equal Distribution','priority'=>'Priority Based','manual'=>\App\Support\ClientFacingText::label('Manual Assignment')]" class="w-full" /></div>
            <label>Daily Lead Limit (0 = Unlimited)<x-text-input variant="compact" type="number" min="0" wire:model="settings.daily_limit" class="mt-1" /></label>
            <label>Leads per assignment batch<x-text-input variant="compact" type="number" min="1" max="1000" wire:model="settings.batch_size" class="mt-1" /></label>
            @unless($isTeamLeader)<div><label class="block mb-1">Timezone</label><x-ui.themed-select wire:model="settings.timezone" :options="array_combine(timezone_identifiers_list(),timezone_identifiers_list())" :searchable="true" class="w-full" /></div>@endunless
            <label>Working hours from<x-text-input variant="compact" type="time" wire:model="settings.start_time" class="mt-1" /></label>
            <label>Working hours to<x-text-input variant="compact" type="time" wire:model="settings.end_time" class="mt-1" /></label>
        </div>
        <fieldset class="text-xs"><legend class="font-semibold mb-2">Working days</legend><div class="flex flex-wrap gap-4">@foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $day=>$label)<label class="flex gap-1 items-center"><input type="checkbox" wire:model="settings.working_days" value="{{ $day }}">{{ $label }}</label>@endforeach</div></fieldset>
        @unless($isTeamLeader)<details class="border border-border rounded-lg p-3 text-xs"><summary class="cursor-pointer font-semibold">Project &amp; campaign eligibility (empty = all in this organization)</summary><div class="grid sm:grid-cols-2 gap-4 mt-3"><fieldset><legend class="font-bold mb-2">Projects</legend>@forelse($projects as $id=>$name)<label class="flex gap-2 mb-2"><input type="checkbox" value="{{ $id }}" wire:model="settings.project_ids">{{ $name }}</label>@empty<p class="text-muted">No mapped projects.</p>@endforelse</fieldset><fieldset><legend class="font-bold mb-2">Campaigns</legend>@forelse($campaigns as $id=>$name)<label class="flex gap-2 mb-2"><input type="checkbox" value="{{ $id }}" wire:model="settings.campaign_ids">{{ $name }}</label>@empty<p class="text-muted">No campaigns.</p>@endforelse</fieldset></div></details>@endunless
        <p class="text-xs text-muted">Inactive or unavailable executives are skipped.</p>

        @if($errors->any())<p class="text-sm text-danger">{{ $errors->first() }}</p>@endif
        <div class="flex gap-2"><button title="Save Settings" aria-label="Save Settings" class="inline-flex items-center gap-2 bg-primary hover:bg-primary-hover text-white rounded-lg px-4 py-2 text-xs font-semibold" wire:loading.attr="disabled"><x-ui.team-icon name="check" /><span>Save Settings</span></button>@unless($isTeamLeader)<button type="button" title="Assign Pending Batch" aria-label="Assign Pending Batch" wire:click="assignPending" wire:loading.attr="disabled" class="border border-border rounded-lg px-4 py-2 text-xs"><x-ui.team-icon name="check" /></button>@endunless</div>
    </form>
    @else<p class="text-xs text-muted">Ask Super Admin to select your assignment team.</p>@endif
    @php
        $columns = collect(['name'=>'Executive','team'=>'Team','availability'=>'Availability','assigned'=>'Total Assigned','today'=>'Assigned Today','pending'=>'Pending Work','capacity'=>'Daily Capacity Remaining'])->map(fn($label,$key)=>['key'=>$key,'label'=>$label,'priority'=>1])->values()->all();
        $columns[2]['render'] = fn ($row) => view('components.ui.assignment-member-control', ['id'=>$row->id, 'name'=>$row->name, 'available'=>$row->available, 'canConfigure'=>$canConfigure])->render();
        if ($canConfigure && !$isTeamLeader) $columns[] = ['key'=>'priority','label'=>'Priority','priority'=>1,'render'=>fn ($row) => view('components.ui.assignment-member-control', ['id'=>$row->id, 'name'=>$row->name, 'available'=>$row->available, 'canConfigure'=>true,'priority'=>true])->render()];
        $rows = collect($statistics)->map(fn($row)=>(object) array_merge($row,['availability'=>$row['available'] ? 'Available' : 'Unavailable','capacity'=>$row['remaining'] ?? 'Unlimited']));
    @endphp
    <x-ui.advanced-table :columns="$columns" :rows="$rows" :show-search="false" :show-filter-dropdown="false" :show-configurations="false" :show-skeleton="false" table-name="organization-assignment-statistics" empty-title="No Sales Executives" empty-message="Add sales executives to your organization teams." />
</div>
