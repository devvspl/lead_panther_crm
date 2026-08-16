<?php

namespace App\Livewire\Replacement;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\LeadReplacement;
use App\Models\Client;

class ClientReplacementHistory extends Component
{
    use WithPagination;

    public function render()
    {
        $user = auth()->user();
        $client = Client::where('organization_id', $user?->organization_id)->first() ?? Client::first();

        $replacements = $client ? LeadReplacement::with(['lead', 'reason', 'replacementLead'])
            ->whereHas('lead', function ($q) use ($client) {
                $q->where('client_id', $client->id);
            })
            ->latest('requested_at')
            ->paginate(10) : LeadReplacement::query()->whereRaw('1 = 0')->paginate(10);

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
