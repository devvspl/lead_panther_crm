<?php

namespace App\Livewire\Leads;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\UploadBatch;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UploadHistory extends Component
{
    use WithPagination;

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

    public function render()
    {
        $batches = UploadBatch::with(['uploader', 'project', 'campaign', 'leadSource'])
            ->latest('id')
            ->paginate(15);

        return view('livewire.leads.upload-history', [
            'batches' => $batches,
        ])->layout('layouts.app');
    }

    public function exportExcel()
    {
        $data = UploadBatch::with(['uploader', 'project', 'campaign', 'leadSource'])
            ->latest('id')
            ->get();
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
