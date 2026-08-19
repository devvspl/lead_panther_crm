<?php

namespace App\Livewire\Leads;

use Livewire\Component;
use App\Livewire\Concerns\HasAdvancedTable;
use App\Models\UploadBatch;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UploadHistory extends Component
{
    use HasAdvancedTable;

    public function mount(): void
    {
        $this->loadColumnPreferences();
    }

    public function tableColumns(): array
    {
        return [
            ['key' => 'id', 'label' => 'Batch ID', 'type' => 'text', 'sortable' => true, 'priority' => 1, 'class' => 'font-mono font-bold text-ink'],
            ['key' => 'created_at', 'label' => 'Upload Date', 'type' => 'date', 'sortable' => true, 'priority' => 1, 'format' => 'M d, Y H:i'],
            ['key' => 'filename', 'label' => 'Source File', 'type' => 'text', 'sortable' => true, 'priority' => 1, 'class' => 'font-mono text-xs font-bold text-ink truncate max-w-xs'],
            ['key' => 'uploader_name', 'label' => 'Uploaded By', 'type' => 'text', 'priority' => 2, 'class' => 'text-ink font-semibold'],
            ['key' => 'project_name', 'label' => 'Target Project', 'type' => 'text', 'priority' => 2, 'class' => 'text-muted'],
            ['key' => 'total_rows', 'label' => 'Total Rows', 'type' => 'text', 'sortable' => true, 'priority' => 1, 'class' => 'font-mono font-bold text-ink'],
            ['key' => 'imported_count', 'label' => 'Imported', 'type' => 'text', 'sortable' => true, 'priority' => 1, 'class' => 'font-mono font-bold text-emerald-600'],
            ['key' => 'failed_count', 'label' => 'Failed', 'type' => 'text', 'sortable' => true, 'priority' => 2, 'class' => 'font-mono font-bold text-rose-600'],
            ['key' => 'status_badge', 'label' => 'Status', 'type' => 'badge', 'priority' => 1, 'badgeMap' => [
                'Completed' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
                'Partial' => 'bg-amber-50 text-amber-700 border border-amber-200',
                'Failed' => 'bg-red-50 text-red-700 border border-red-200',
                'Empty' => 'bg-slate-50 text-slate-700 border border-slate-200',
            ]],
            ['key' => 'actions', 'label' => 'Actions', 'type' => 'upload_actions', 'priority' => 1],
        ];
    }

    public function quickFilters(): array
    {
        return [
            ['key' => 'all', 'label' => 'All Batches'],
            ['key' => 'completed', 'label' => 'Clean Imports'],
            ['key' => 'failed', 'label' => 'Has Failures'],
        ];
    }

    public function downloadErrorCsv(int $batchId): StreamedResponse
    {
        $batch = UploadBatch::findOrFail($batchId);
        $errors = $batch->error_log ?? [];

        $filename = "upload_errors_batch_{$batchId}.csv";

        return response()->streamDownload(function () use ($errors) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Row Number', 'Raw Data', 'Failure Reason']);

            foreach ($errors as $err) {
                fputcsv($output, [$err['row'] ?? '', $err['data'] ?? '', $err['reason'] ?? '']);
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    protected function getFilteredQuery()
    {
        $query = UploadBatch::with(['uploader', 'project', 'campaign', 'leadSource']);

        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('filename', 'like', '%' . $this->search . '%')
                  ->orWhereHas('uploader', fn($uq) => $uq->where('name', 'like', '%' . $this->search . '%'))
                  ->orWhereHas('project', fn($pq) => $pq->where('name', 'like', '%' . $this->search . '%'));
            });
        }

        if ($this->statusFilter === 'completed') {
            $query->where('failed_count', 0)->where('imported_count', '>', 0);
        } elseif ($this->statusFilter === 'failed') {
            $query->where('failed_count', '>', 0);
        }

        if (!empty($this->sortField)) {
            $query->orderBy($this->sortField, $this->sortDirection);
        } else {
            $query->latest('id');
        }

        return $query;
    }

    public function render()
    {
        $batches = $this->getFilteredQuery()->paginate($this->perPage);

        return view('livewire.leads.upload-history', [
            'batches' => $batches,
        ])->layout('layouts.app');
    }

    public function exportExcel()
    {
        $data = $this->getFilteredQuery()->get();
        $filename = "bulk-upload-batches_" . now()->format('Y-m-d') . ".xlsx";

        $headings = ['Batch ID', 'Filename', 'Uploaded By', 'Project', 'Total Rows', 'Imported Count', 'Skipped Count', 'Failed Count', 'Created At'];
        $columns = [
            'id',
            'filename',
            fn($b) => $b->uploader?->name ?: 'System',
            fn($b) => $b->project?->name ?: 'N/A',
            'total_rows',
            'imported_count',
            'skipped_count',
            'failed_count',
            fn($b) => $b->created_at ? $b->created_at->format('M d, Y H:i') : '',
        ];

        $subtitle = "Exported " . now()->format('d M Y, H:i T');

        $this->dispatch('toast', type: 'success', message: 'Export ready — downloading now.');

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\BaseStyledExport(
                data: $data,
                title: 'Bulk Lead Upload Batches History',
                subtitle: $subtitle,
                headings: $headings,
                columns: $columns
            ),
            $filename
        );
    }
}
