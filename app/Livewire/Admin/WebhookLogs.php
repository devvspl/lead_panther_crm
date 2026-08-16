<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\WebhookLog;
use App\Jobs\ProcessInboundLeadJob;

class WebhookLogs extends Component
{
    use WithPagination;

    public string $filterStatus = ''; // processed, failed, pending
    public string $dateRange = '';
    public ?string $customFrom = null;
    public ?string $customTo = null;

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatingDateRange(): void
    {
        $this->resetPage();
    }

    public function retryLog(int $id): void
    {
        $log = WebhookLog::find($id);
        if ($log) {
            ProcessInboundLeadJob::dispatchSync($log);
            $this->dispatch('toast', type: 'success', message: "Webhook Log #{$log->id} re-processed successfully.");
        }
    }

    public function render()
    {
        $query = WebhookLog::with('portalAccount');

        if ($this->filterStatus === 'processed') {
            $query->where('processed', true)->whereNull('error_message');
        } elseif ($this->filterStatus === 'failed') {
            $query->whereNotNull('error_message');
        } elseif ($this->filterStatus === 'pending') {
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

        $logs = $query->latest('received_at')->paginate(15);

        return view('livewire.admin.webhook-logs', [
            'logs' => $logs,
        ])->layout('layouts.app');
    }

    public function exportExcel()
    {
        $query = WebhookLog::with('portalAccount');

        if ($this->filterStatus === 'processed') {
            $query->where('processed', true)->whereNull('error_message');
        } elseif ($this->filterStatus === 'failed') {
            $query->whereNotNull('error_message');
        } elseif ($this->filterStatus === 'pending') {
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

        $data = $query->latest('received_at')->get();
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

        $subtitle = "Exported " . now()->format('d M Y, H:i T') . ($this->filterStatus ? " | Status: {$this->filterStatus}" : '');

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
