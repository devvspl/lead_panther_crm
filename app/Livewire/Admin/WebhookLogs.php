<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Livewire\Concerns\HasAdvancedTable;
use App\Models\WebhookLog;
use App\Jobs\ProcessInboundLeadJob;

class WebhookLogs extends Component
{
    use HasAdvancedTable;

    public string $dateRange = '';
    public ?string $customFrom = null;
    public ?string $customTo = null;

    public function mount(): void
    {
        $this->loadColumnPreferences();
    }

    public function tableColumns(): array
    {
        return [
            ['key' => 'id', 'label' => 'ID', 'type' => 'text', 'sortable' => true, 'priority' => 1, 'class' => 'font-mono text-muted text-[11px]'],
            ['key' => 'received_at', 'label' => 'Received At', 'type' => 'date', 'sortable' => true, 'priority' => 1, 'format' => 'M d, Y H:i:s'],
            ['key' => 'portal_account_name', 'label' => 'Source / Provider', 'type' => 'text', 'priority' => 1, 'class' => 'font-bold text-ink'],
            ['key' => 'status_badge', 'label' => 'Status', 'type' => 'badge', 'priority' => 1, 'badgeMap' => [
                'Processed' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
                'Failed' => 'bg-red-50 text-red-700 border border-red-200',
                'Pending' => 'bg-amber-50 text-amber-700 border border-amber-200',
            ]],
            ['key' => 'payload_preview', 'label' => 'Payload Preview', 'type' => 'text', 'priority' => 2, 'class' => 'font-mono text-[11px] text-muted max-w-sm truncate'],
            ['key' => 'error_message', 'label' => 'Error Details', 'type' => 'text', 'priority' => 2, 'class' => 'font-mono text-[11px] text-rose-600 max-w-xs truncate'],
            ['key' => 'actions', 'label' => 'Actions', 'type' => 'webhook_actions', 'priority' => 1],
        ];
    }

    public function quickFilters(): array
    {
        return [
            ['key' => 'all', 'label' => 'All Webhooks'],
            ['key' => 'processed', 'label' => 'Processed'],
            ['key' => 'failed', 'label' => 'Failed / Errors'],
            ['key' => 'pending', 'label' => 'Pending'],
        ];
    }

    public function retryLog(int $id): void
    {
        $log = WebhookLog::find($id);
        if ($log) {
            ProcessInboundLeadJob::dispatchSync($log);
            $this->dispatch('toast', type: 'success', message: "Webhook Log #{$log->id} re-processed successfully.");
        }
    }

    protected function getFilteredQuery()
    {
        $query = WebhookLog::with('portalAccount');

        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('source_provider', 'like', '%' . $this->search . '%')
                  ->orWhere('error_message', 'like', '%' . $this->search . '%')
                  ->orWhere('payload', 'like', '%' . $this->search . '%')
                  ->orWhereHas('portalAccount', fn($pq) => $pq->where('account_name', 'like', '%' . $this->search . '%'));
            });
        }

        if ($this->statusFilter === 'processed') {
            $query->where('processed', true)->whereNull('error_message');
        } elseif ($this->statusFilter === 'failed') {
            $query->whereNotNull('error_message');
        } elseif ($this->statusFilter === 'pending') {
            $query->where('processed', false);
        }

        if ($this->dateRange) {
            if ($this->dateRange === 'today') {
                $query->whereDate('received_at', now());
            } elseif ($this->dateRange === 'week') {
                $query->where('received_at', '>=', now()->subDays(7));
            } elseif ($this->dateRange === 'month') {
                $query->where('received_at', '>=', now()->subDays(30));
            } elseif ($this->dateRange === 'custom' && $this->customFrom && $this->customTo) {
                $query->whereBetween('received_at', [$this->customFrom . ' 00:00:00', $this->customTo . ' 23:59:59']);
            }
        }

        if (!empty($this->sortField)) {
            $query->orderBy($this->sortField, $this->sortDirection);
        } else {
            $query->latest('received_at');
        }

        return $query;
    }

    public function render()
    {
        $logs = $this->getFilteredQuery()->paginate($this->perPage);

        return view('livewire.admin.webhook-logs', [
            'logs' => $logs,
        ])->layout('layouts.app');
    }

    public function exportExcel()
    {
        $data = $this->getFilteredQuery()->get();
        $filename = "webhook-logs_" . now()->format('Y-m-d') . ".xlsx";

        $headings = ['Log ID', 'Received At', 'Portal Account', 'Source Provider', 'Status', 'Payload Preview', 'Error Message'];
        $columns = [
            'id',
            fn($l) => $l->received_at ? $l->received_at->format('M d, Y H:i:s') : '',
            fn($l) => $l->portalAccount?->account_name ?: 'System Default',
            'source_provider',
            fn($l) => $l->processed ? 'processed' : ($l->error_message ? 'failed' : 'pending'),
            fn($l) => \Illuminate\Support\Str::limit(json_encode($l->payload), 60),
            fn($l) => $l->error_message ?: 'None',
        ];

        $subtitle = "Exported " . now()->format('d M Y, H:i T') . ($this->statusFilter ? " | Status: {$this->statusFilter}" : '');

        $this->dispatch('toast', type: 'success', message: 'Export ready — downloading now.');

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\BaseStyledExport(
                data: $data,
                title: 'Inbound Webhook Logs Directory',
                subtitle: $subtitle,
                headings: $headings,
                columns: $columns,
                statusColumns: ['status']
            ),
            $filename
        );
    }
}
