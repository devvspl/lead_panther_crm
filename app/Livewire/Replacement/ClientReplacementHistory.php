<?php

namespace App\Livewire\Replacement;

use Livewire\Component;
use App\Models\LeadReplacement;
use App\Models\Client;
use App\Livewire\Concerns\HasAdvancedTable;

class ClientReplacementHistory extends Component
{
    use HasAdvancedTable;

    public function tableColumns(): array
    {
        return [
            ['key' => 'lead', 'label' => 'Original Lead', 'render' => fn($row) => '<div class="font-bold text-ink">' . e($row->lead?->name) . '</div><div class="text-[10px] text-muted font-mono">' . e($row->lead?->lead_code) . '</div>', 'sortable' => false, 'priority' => 1],
            ['key' => 'reason', 'label' => 'Reason', 'formatter' => fn($v, $row) => $row->reason?->label, 'sortable' => false, 'priority' => 1],
            ['key' => 'requested_at', 'label' => 'Requested At', 'type' => 'date', 'sortable' => true, 'priority' => 2],
            ['key' => 'sla_status', 'label' => 'SLA Status', 'render' => fn($row) => '<span class="px-2 py-0.5 text-[10px] font-bold rounded-pill ' . ($row->sla_met ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200') . '">' . ($row->sla_met ? 'SLA Met (≤30m)' : 'SLA Missed') . '</span>', 'sortable' => false, 'priority' => 2],
            ['key' => 'status', 'label' => 'Outcome', 'type' => 'badge', 'badgeMap' => [
                'approved' => 'bg-green-50 text-green-700 border border-green-200',
                'rejected' => 'bg-red-50 text-red-700 border border-red-200',
                'pending' => 'bg-amber-50 text-amber-700 border border-amber-200',
            ], 'sortable' => true, 'priority' => 1],
            ['key' => 'notes', 'label' => 'Replacement Lead / Notes', 'render' => function($row) {
                if ($row->status === 'approved' && $row->replacementLead) {
                    return '<span class="font-bold text-success font-mono text-xs">New Lead: ' . e($row->replacementLead->lead_code) . '</span>';
                } elseif ($row->status === 'rejected') {
                    return '<span class="text-danger italic text-xs">Reason: ' . e($row->resolution_note ?: 'Ineligible per SLA policy') . '</span>';
                }
                return '<span class="text-muted text-xs">Under Review</span>';
            }, 'sortable' => false, 'priority' => 1],
        ];
    }

    public function render()
    {
        $user = auth()->user();
        $client = Client::where('organization_id', $user?->organization_id)->first() ?? Client::first();

        if ($client) {
            $query = LeadReplacement::with(['lead', 'reason', 'replacementLead'])
                ->whereHas('lead', function ($q) use ($client) {
                    $q->where('client_id', $client->id);
                });

            if (!empty($this->search)) {
                $query->where(function ($q) {
                    $q->whereHas('lead', function ($lq) {
                        $lq->where('name', 'like', "%{$this->search}%")
                           ->orWhere('lead_code', 'like', "%{$this->search}%");
                    })->orWhere('resolution_note', 'like', "%{$this->search}%");
                });
            }

            if ($this->statusFilter && $this->statusFilter !== 'all') {
                $query->where('status', $this->statusFilter);
            }

            $sortField = in_array($this->sortField, ['requested_at', 'status', 'id']) ? $this->sortField : 'requested_at';
            $sortDir = $this->sortDirection === 'asc' ? 'asc' : 'desc';

            $replacements = $query->orderBy($sortField, $sortDir)->paginate($this->perPage);
        } else {
            $replacements = LeadReplacement::query()->whereRaw('1 = 0')->paginate($this->perPage);
        }

        return view('livewire.replacement.client-replacement-history', [
            'replacements' => $replacements,
            'client' => $client,
        ])->layout('layouts.app');
    }

    public function exportExcel()
    {
        $user = auth()->user();
        $client = Client::where('organization_id', $user?->organization_id)->first() ?? Client::first();

        $query = $client ? LeadReplacement::with(['lead', 'reason', 'replacementLead'])
            ->whereHas('lead', function ($q) use ($client) {
                $q->where('client_id', $client->id);
            }) : LeadReplacement::query()->whereRaw('1 = 0');

        $data = $query->latest('requested_at')->get();
        $clientSlug = $client ? \Illuminate\Support\Str::slug($client->name) : 'client';
        $filename = "replacement-history_{$clientSlug}_" . now()->format('Y-m-d') . ".xlsx";

        $headings = ['Requested At', 'Lead Code', 'Reason', 'Status', 'Resolution / Note'];
        $columns = [
            fn($r) => $r->requested_at ? ($r->requested_at instanceof \Carbon\CarbonInterface ? $r->requested_at->format('M d, Y H:i') : \Carbon\Carbon::parse($r->requested_at)->format('M d, Y H:i')) : '',
            fn($r) => $r->lead?->lead_code ?: 'N/A',
            fn($r) => $r->reason?->reason_name ?: 'N/A',
            fn($r) => $r->status,
            fn($r) => $r->resolution_note ?: ($r->replacementLead ? "Replaced by {$r->replacementLead->lead_code}" : 'Under Review'),
        ];

        $subtitle = "Exported " . now()->format('d M Y, H:i T') . ($client ? " | Client: {$client->name}" : '');

        $this->dispatch('toast', type: 'success', message: 'Export ready — downloading now.');

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\BaseStyledExport(
                data: $data,
                title: 'Client Replacement History',
                subtitle: $subtitle,
                headings: $headings,
                columns: $columns,
                statusColumns: ['status']
            ),
            $filename
        );
    }
}
