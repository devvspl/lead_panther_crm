<section class="space-y-4" wire:poll.20s>
    <div><h2 class="text-lg font-bold text-ink">Internal Processing Queue</h2><p class="text-xs text-muted">Call, verify and hand over leads. Client stages, credits and SLA start only on client delivery. Dates use {{ config('app.timezone') }}.</p></div>
    <div class="grid sm:grid-cols-2 lg:grid-cols-5 gap-3 rounded-xl border border-border bg-surface p-4 text-xs">
        <input wire:model.live.debounce.300ms="search" placeholder="Lead code, name or phone" aria-label="Search internal leads" class="rounded-lg border border-border bg-canvas p-2">
        <select wire:model.live="organizationId" aria-label="Client organization" class="rounded-lg border border-border bg-canvas p-2"><option value="">All authorized clients</option>@foreach($organizations as $org)<option value="{{ $org->id }}">{{ $org->name }}</option>@endforeach</select>
        <select wire:model.live="statusFilter" aria-label="Processing status" class="rounded-lg border border-border bg-canvas p-2"><option value="">All processing statuses</option>@foreach($statuses as $status)<option value="{{ $status->code }}">{{ $status->label }}</option>@endforeach</select>
        <input type="date" wire:model.live="dateFrom" aria-label="Received from" class="rounded-lg border border-border bg-canvas p-2"><input type="date" wire:model.live="dateTo" aria-label="Received to" class="rounded-lg border border-border bg-canvas p-2">
    </div>
    <div class="overflow-x-auto rounded-xl border border-border bg-surface"><table class="w-full text-left text-xs"><thead class="bg-canvas text-muted"><tr>@foreach(['Lead / Client', 'Internal Executive', 'Processing Status', 'Calling', 'Last Contacted', 'Next Follow-up', 'Verification', 'Client Handover', 'Actions'] as $heading)<th class="p-3 whitespace-nowrap">{{ $heading }}</th>@endforeach</tr></thead><tbody>
    @forelse($records as $record)
        @php $actions = $service->availableInternalActions($record, auth()->user()); @endphp
        <tr class="border-t border-border" wire:key="internal-record-{{ $record->id }}">
            <td class="p-3 min-w-44"><button wire:click="openRecord({{ $record->id }})" class="text-primary font-semibold">{{ $record->lead->lead_code }}</button><p class="text-ink">{{ $record->lead->name }}</p><p class="text-muted">{{ $record->organization->name }}</p></td>
            <td class="p-3 text-ink">{{ $record->assignee?->name ?? 'Unassigned' }}</td><td class="p-3"><span class="rounded-full bg-primary/10 text-primary px-2 py-1 whitespace-nowrap">{{ $record->status?->label }}</span></td>
            <td class="p-3 text-muted">{{ $record->calling_status ? ucwords(str_replace('_', ' ', $record->calling_status)) : 'Pending' }}<p>{{ $record->call_attempts }} attempts</p></td>
            <td class="p-3 text-muted whitespace-nowrap">{{ $record->last_contacted_at?->format('M d, H:i') ?? 'Not contacted' }}</td><td class="p-3 text-muted whitespace-nowrap">{{ $record->next_followup_at?->format('M d, H:i') ?? 'None' }}</td>
            <td class="p-3 text-ink">{{ ucfirst($record->verification_status) }}</td><td class="p-3 text-ink">{{ $record->forwarded_at ? 'Delivered to client' : ($record->forwarding_error ? 'Failed; awaiting retry' : ($record->handover_requested_at ? 'Queued for client delivery' : 'Not forwarded')) }}</td>
            <td class="p-3"><div class="flex flex-wrap gap-2 min-w-44 text-primary"><button wire:click="openRecord({{ $record->id }})">View Lead</button>@if(in_array('call', $actions))<button wire:click="openRecord({{ $record->id }}, 'call')">Call Lead</button>@endif
            @if(in_array('verify', $actions))<button wire:click="openRecord({{ $record->id }}, 'verify')">Verify</button>@endif
            @if(in_array('forward', $actions) || ($record->forwarding_error && !$record->forwarded_at))<button wire:click="forward({{ $record->id }})" wire:confirm="Forward this verified lead to the client? Client delivery rules and credits still apply.">Forward to Client</button>@endif
            @if(auth()->user()->hasAnyRole(['Super Admin','super-admin']) && !$record->handover_requested_at)<button wire:click="openRecord({{ $record->id }}, 'assign')">Assign</button>@endif</div></td>
        </tr>
    @empty<tr><td colspan="9" class="p-8 text-center text-muted">No authorized internal leads match these filters.</td></tr>@endforelse
    </tbody></table><div class="p-3">{{ $records->links() }}</div></div>
    <x-modal name="internal-processing-action" :show="$selectedId !== null" maxWidth="4xl">
        @if($selected)
            @php $actions = $service->availableInternalActions($selected, auth()->user()); $allowedCodes = $selected->organization->internal_processing_config['status_codes'] ?? []; @endphp
            <div class="p-5 space-y-4 max-h-[88vh] overflow-y-auto">
                <header class="flex justify-between gap-3"><div><h3 class="font-bold text-ink">{{ $selected->lead->lead_code }} · {{ $selected->lead->name }}</h3><p class="text-xs text-muted">{{ $selected->organization->name }} · {{ $selected->status?->label }}</p></div><button wire:click="closeRecord" aria-label="Close">✕</button></header>
                <div class="grid sm:grid-cols-2 gap-3 text-xs text-ink"><div>Mobile: <a href="tel:{{ $selected->lead->mobile }}" class="text-primary">{{ $selected->lead->mobile }}</a></div><div>Email: {{ $selected->lead->email ?? 'Not provided' }}</div><div>Call attempts: {{ $selected->call_attempts }}</div><div>Verification: {{ ucfirst($selected->verification_status) }}</div><div>Verified at: {{ $selected->verified_at?->toDateTimeString() ?? 'Not verified' }}</div><div>Handover at: {{ $selected->forwarded_at?->toDateTimeString() ?? 'Not delivered' }}</div></div>
                <p class="text-sm text-ink whitespace-pre-wrap">{{ $selected->calling_remarks ?? 'No internal calling remarks yet.' }}</p>
                <div class="grid sm:grid-cols-2 gap-3 text-xs text-ink"><div>Project: {{ $selected->lead->campaign?->project?->name ?? 'Not linked' }}</div><div>Campaign: {{ $selected->lead->campaign?->name ?? 'Not linked' }}</div>@canViewLeadSource<div>Source: {{ $selected->lead->leadSource?->name ?? 'Not recorded' }}</div>@endcanViewLeadSource<div>City: {{ $selected->lead->city ?? 'Not recorded' }}</div><div>Budget: {{ $selected->lead->budget_display }}</div></div>
                @if($selected->forwarding_error)<p class="text-xs text-red-600">{{ $selected->forwarding_error }}</p>@endif
                <div class="flex flex-wrap gap-2 text-xs">@foreach(['call' => 'Log Call', 'status' => 'Update Status / Hold', 'remarks' => 'Add Remarks', 'followup' => 'Schedule Follow-up', 'verify' => 'Verify Lead'] as $mode => $label)@if(in_array($mode, $actions))<button wire:click="openRecord({{ $selected->id }}, '{{ $mode }}')" class="rounded-lg border border-border px-3 py-2">{{ $label }}</button>@endif @endforeach
                    @if(in_array('forward', $actions))<button wire:click="forward({{ $selected->id }})" wire:confirm="Confirm verified lead handover to the client?" class="rounded-lg bg-primary text-white px-3 py-2">Forward to Client</button>@endif
                </div>
                @if($action !== 'view')
                    <form wire:submit="saveAction" class="space-y-3 border border-border rounded-lg p-4">
                        <h4 class="text-sm font-semibold text-ink">{{ ucwords($action) }}</h4>
                        @if($action === 'assign')<livewire:shared.searchable-select wire:model.live="assignUserId" :options="$assignees->pluck('name', 'id')->all()" placeholder="Select internal executive" class="block w-full" :key="'internal-assignee-'.$selected->id" />@endif
                        @if($action === 'call')<a href="tel:{{ $selected->lead->mobile }}" class="inline-block text-xs text-primary">Call {{ $selected->lead->mobile }}</a><select wire:model="callingStatus" aria-label="Call result" class="block w-full border border-border bg-canvas rounded-lg p-2 text-xs"><option value="">Choose call result</option><option value="connected">Connected</option><option value="not_answered">Not answered</option><option value="busy">Busy</option><option value="failed">Failed</option></select>@endif
                        @if($action === 'status')<select wire:model="statusCode" aria-label="New processing status" class="block w-full border border-border bg-canvas rounded-lg p-2 text-xs"><option value="">Choose status</option>@foreach($statuses->where('is_system', false)->whereIn('code', $allowedCodes) as $status)<option value="{{ $status->code }}">{{ $status->label }}</option>@endforeach</select>@endif
                        @if(in_array($action, ['call','remarks','status']))<textarea wire:model="remarks" placeholder="Internal calling remarks" class="block w-full border border-border bg-canvas rounded-lg p-2 text-xs" rows="3"></textarea>@endif
                        @if($action === 'followup')<label class="text-xs text-muted">Next follow-up ({{ config('app.timezone') }})<input type="datetime-local" wire:model="followupAt" class="block w-full border border-border bg-canvas rounded-lg p-2"></label>@endif
                        @if($action === 'verify')<p class="text-xs text-muted">Confirm that the connected call and recorded details successfully verify this customer.</p>@endif
                        @if($errors->any())<p class="text-xs text-red-600">{{ $errors->first() }}</p>@endif
                        <button type="submit" wire:loading.attr="disabled" class="rounded-lg bg-primary text-white text-xs px-4 py-2">Save {{ ucfirst($action) }}</button>
                    </form>
                @endif
                <section class="border-t border-border pt-3"><h4 class="font-semibold text-sm text-ink mb-2">Processing & Handover History</h4>@forelse($history as $event)<div class="border-b border-border py-2 text-xs"><strong class="text-ink">{{ ucwords(str_replace('_', ' ', $event->action)) }}</strong><span class="text-muted"> · {{ $event->created_at->format('M d, Y H:i') }} · {{ $event->user?->name ?? 'System' }}</span><p class="text-ink whitespace-pre-wrap">{{ $event->details['input']['remarks'] ?? $event->details['after']['calling_remarks'] ?? '' }}</p>@if(isset($event->details['input']['calling_status']))<p class="text-muted">Call: {{ $event->details['input']['calling_status'] }}</p>@endif</div>@empty<p class="text-xs text-muted">No processing events recorded.</p>@endforelse<div class="pt-2">{{ $history->links() }}</div></section>
            </div>
        @endif
    </x-modal>
</section>
