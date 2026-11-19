<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-bold text-ink">Archived Leads</h1>
        <x-ui.button wire:click="exportExcel">Export to Excel</x-ui.button>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 rounded-xl border border-border bg-surface p-4">
        <label class="text-sm text-ink">Archived by<x-ui.themed-select class="w-full" searchable  wire:model.live="archivedBy" trigger-class="mt-1 block w-full rounded-lg border-border bg-surface text-ink"><option value="">All users</option>@foreach($archivers as $actor)<option value="{{ $actor->id }}">{{ $actor->name }}</option>@endforeach</x-ui.themed-select></label>
        <label class="text-sm text-ink">Stage<x-ui.themed-select class="w-full" searchable  wire:model.live="stage" trigger-class="mt-1 block w-full rounded-lg border-border bg-surface text-ink"><option value="">All stages</option>@foreach($stages as $value)<option value="{{ $value }}">{{ ucwords(str_replace('_',' ',$value)) }}</option>@endforeach</x-ui.themed-select></label>
        <label class="text-sm text-ink">Archived from<x-text-input type="date" wire:model.live="dateFrom" class="mt-1 block w-full" /></label>
        <label class="text-sm text-ink">Archived to<x-text-input type="date" wire:model.live="dateTo" class="mt-1 block w-full" /></label>
    </div>
    @foreach(['dateFrom','dateTo','archivedBy'] as $field)@error($field)<p class="text-sm text-danger">{{ $message }}</p>@enderror@endforeach
    <x-ui.advanced-table :columns="$this->tableColumns()" :rows="$leads" :visibleColumns="$visibleColumns" :sortField="$sortField" :sortDirection="$sortDirection" :showFilterDropdown="false" searchPlaceholder="Search archived leads or reasons..." emptyTitle="No Archived Leads" />

    <x-modal name="archived-lead-action" maxWidth="lg">
        <form wire:submit="execute" class="p-6 space-y-4">
            <h2 class="text-lg font-bold text-ink">{{ $action === 'restore' ? 'Restore Lead' : 'Permanently Delete Lead' }}</h2>
            @if($action === 'restore')
                <p class="text-sm text-muted">Restore the saved stage and assignment. History will be available again.</p>
                <label class="flex items-center gap-2 text-sm text-ink"><input type="checkbox" wire:model="reassign"> Allow automatic assignment after restoration</label>
            @else
                <p class="text-sm text-danger">This permanently deletes the lead and its dependent activity. This cannot be undone. Financial and transfer records must be retained.</p>
                <label class="block text-sm text-ink">Reason (optional)<textarea wire:model="reason" maxlength="2000" class="mt-1 w-full rounded-lg border-border bg-surface text-ink"></textarea></label>
            @endif
            <label class="flex items-center gap-2 text-sm text-ink"><input type="checkbox" wire:model="confirmed"> I confirm this action</label>
            @foreach(['confirmed','reason','deletion'] as $field)@error($field)<p class="text-sm text-danger">{{ $message }}</p>@enderror@endforeach
            <div class="flex justify-end gap-2 border-t border-border pt-4">
                <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'archived-lead-action')">Cancel</x-ui.button>
                <x-ui.button type="submit" wire:loading.attr="disabled">{{ $action === 'restore' ? 'Restore Lead' : 'Permanently Delete' }}</x-ui.button>
            </div>
        </form>
    </x-modal>

    <x-modal name="archived-lead-details" maxWidth="4xl">
        <div class="p-6 space-y-4 text-ink">
            <div class="flex justify-between items-center"><h2 class="text-lg font-bold">Archived Lead Details</h2><button type="button" x-on:click="$dispatch('close-modal', 'archived-lead-details')" title="Close details" aria-label="Close details"><x-ui.team-icon name="close" /></button></div>
            @if($detail)
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                    @foreach(['lead_code'=>'Lead ID','name'=>'Name','mobile'=>'Mobile','email'=>'Email','city'=>'City','budget'=>'Budget','property_type'=>'Property Type','requirement'=>'Requirement','status'=>'Status','current_stage'=>'Stage','client.organization.name'=>'Organization','assignedTo.name'=>'Executive','archive_reason'=>'Archive Reason','archivedBy.name'=>'Archived By','archived_at'=>'Archived At'] as $field=>$label)
                        <div class="rounded-lg bg-canvas p-3 border border-border"><dt class="text-muted">{{ $label }}</dt><dd class="break-words">{{ data_get($detail,$field) ?? '—' }}</dd></div>
                    @endforeach
                </dl>
                <h3 class="font-semibold">Assignment History</h3>
                @forelse($assignments as $entry)<p class="text-sm">{{ $entry->assignee?->name ?? 'Former user' }} · {{ $entry->assigned_at }} · {{ $entry->unassigned_at ?? 'Current assignment' }}</p>@empty<p class="text-sm text-muted">No assignments recorded.</p>@endforelse
                <h3 class="font-semibold">Follow-ups</h3>
                @forelse($followups as $entry)<p class="text-sm">{{ $entry->due_at }} · {{ $entry->status }} · {{ $entry->note }}</p>@empty<p class="text-sm text-muted">No follow-ups recorded.</p>@endforelse
                @foreach($retainedRecords as $label=>$records)
                    @if($records->isNotEmpty())
                        <details class="rounded-lg border border-border p-3"><summary class="cursor-pointer font-semibold">{{ $label }} ({{ $records->count() }})</summary>
                            @foreach($records as $record)
                                <dl class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2 border-t border-border pt-3 text-sm">
                                    @foreach($record->attributesToArray() as $key=>$value)
                                        <div><dt class="text-muted">{{ ucwords(str_replace('_',' ',$key)) }}</dt><dd class="break-words">{{ is_array($value) ? json_encode($value) : ($value ?? '—') }}</dd></div>
                                    @endforeach
                                </dl>
                            @endforeach
                        </details>
                    @endif
                @endforeach
                <h3 class="font-semibold">Activity &amp; Archive History</h3>
                @forelse($history as $entry)<div class="rounded-lg border border-border p-3 text-sm"><strong>{{ $entry->action }}</strong><p>{{ $entry->user?->name ?? 'Former user' }} · {{ $entry->created_at }}</p><p class="break-words">{{ $entry->from_value }}</p><p class="break-words">{{ $entry->to_value }}</p></div>@empty<p class="text-sm text-muted">No activity recorded.</p>@endforelse
            @endif
        </div>
    </x-modal>
</div>
