<?php

namespace App\Livewire\Leads;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\Project;
use App\Models\Campaign;
use App\Models\LeadSource;
use App\Models\Lead;
use App\Models\UploadBatch;
use App\Events\LeadAssigned;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class BulkLeadUpload extends Component
{
    use WithFileUploads;

    public $file;
    public int $step = 1;

    // File preview & stored row data
    public array $headers = [];
    public array $previewRows = [];
    public array $allCsvRows = [];
    public array $columnMapping = []; // [target_field => selected_header_index]

    // Step 2 Attribution
    public ?int $projectId = null;
    public ?int $campaignId = null;
    public ?int $leadSourceId = null;

    // Processing Results
    public ?UploadBatch $completedBatch = null;

    public array $availableFields = [
        'name' => 'Full Name',
        'mobile' => 'Mobile Number (Required)',
        'email' => 'Email Address',
        'city' => 'City / Location',
        'budget' => 'Budget (INR)',
        'property_type' => 'Property Type (2BHK/3BHK)',
        'requirement' => 'Requirement / Notes',
    ];

    public function updatedFile(): void
    {
        $this->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
        ]);

        $path = $this->file->getRealPath();
        $handle = fopen($path, 'r');

        if ($handle !== false) {
            $this->headers = [];
            $this->previewRows = [];
            $this->allCsvRows = [];

            // Read Header Row
            $headerRow = fgetcsv($handle);
            if ($headerRow) {
                foreach ($headerRow as $index => $col) {
                    $this->headers[$index] = trim($col);
                }
            }

            // Read All Data Rows
            while (($row = fgetcsv($handle)) !== false) {
                if (!empty(array_filter($row))) {
                    $this->allCsvRows[] = $row;
                }
            }

            fclose($handle);

            $this->previewRows = array_slice($this->allCsvRows, 0, 10);
            $this->autoGuessColumnMapping();
        }
    }

    protected function autoGuessColumnMapping(): void
    {
        $this->columnMapping = [];

        foreach ($this->headers as $index => $header) {
            $normalized = strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', $header)));

            if (in_array($normalized, ['phone', 'mobile', 'contact', 'cell', 'number', 'mobilenumber'])) {
                $this->columnMapping['mobile'] = (string) $index;
            } elseif (in_array($normalized, ['name', 'fullname', 'leadname', 'customername', 'clientname'])) {
                $this->columnMapping['name'] = (string) $index;
            } elseif (in_array($normalized, ['email', 'mail', 'emailaddress'])) {
                $this->columnMapping['email'] = (string) $index;
            } elseif (in_array($normalized, ['city', 'location', 'town', 'address'])) {
                $this->columnMapping['city'] = (string) $index;
            } elseif (in_array($normalized, ['budget', 'price', 'amount', 'investment'])) {
                $this->columnMapping['budget'] = (string) $index;
            } elseif (in_array($normalized, ['propertytype', 'property', 'unittype', 'configuration', 'type'])) {
                $this->columnMapping['property_type'] = (string) $index;
            } elseif (in_array($normalized, ['requirement', 'notes', 'description', 'remarks', 'comments'])) {
                $this->columnMapping['requirement'] = (string) $index;
            }
        }
    }

    public function proceedToAttribution(): void
    {
        if (empty($this->columnMapping['mobile'])) {
            $this->dispatch('toast', type: 'error', message: 'Please map a column to Mobile Number (Required).');
            return;
        }

        $this->step = 2;
    }

    public function processBatch(): void
    {
        $this->validate([
            'projectId' => 'required|exists:projects,id',
        ]);

        try {
            $project = Project::findOrFail($this->projectId);

            if (empty($this->allCsvRows) && $this->file) {
                $this->updatedFile();
            }

            $totalRows = 0;
            $importedCount = 0;
            $skippedCount = 0;
            $failedCount = 0;
            $errorLog = [];

            foreach ($this->allCsvRows as $row) {
                $totalRows++;

                // Map Row Data
                $rowData = [];
                foreach ($this->columnMapping as $field => $colIdx) {
                    if (isset($row[$colIdx])) {
                        $rowData[$field] = trim($row[$colIdx]);
                    }
                }

                $mobile = $rowData['mobile'] ?? '';
                // Clean mobile string
                $cleanMobile = preg_replace('/[^0-9]/', '', $mobile);

                // Row Validation
                if (empty($cleanMobile) || strlen($cleanMobile) < 10) {
                    $failedCount++;
                    $errorLog[] = [
                        'row' => $totalRows,
                        'data' => implode(', ', $row),
                        'reason' => 'Invalid or missing mobile number format.',
                    ];
                    continue;
                }

                $formattedMobile = (strlen($cleanMobile) === 10) ? '+91' . $cleanMobile : '+' . $cleanMobile;

                // 90-Day Duplicate Check Pipeline
                $isDuplicate = Lead::where('project_id', $this->projectId)
                    ->where('mobile', $formattedMobile)
                    ->where('created_at', '>=', now()->subDays(90))
                    ->exists();

                if ($isDuplicate) {
                    $skippedCount++;
                    continue;
                }

                // Create Inbound Lead
                static $seq = 5000;
                $seq++;

                $effectiveSourceId = $this->leadSourceId ?? (LeadSource::first()?->id ?? LeadSource::create(['name' => 'csv_import'])->id);

                $lead = Lead::create([
                    'lead_code' => 'LP-' . date('Y') . '-' . str_pad($seq, 8, '0', STR_PAD_LEFT),
                    'client_id' => $project->client_id,
                    'project_id' => $project->id,
                    'campaign_id' => $this->campaignId,
                    'lead_source_id' => $effectiveSourceId,
                    'name' => $rowData['name'] ?? 'CSV Imported Lead',
                    'mobile' => $formattedMobile,
                    'email' => $rowData['email'] ?? null,
                    'city' => $rowData['city'] ?? null,
                    'budget' => !empty($rowData['budget']) ? (float) preg_replace('/[^0-9.]/', '', $rowData['budget']) : null,
                    'property_type' => $rowData['property_type'] ?? null,
                    'requirement' => $rowData['requirement'] ?? null,
                    'status' => 'assigned',
                    'current_stage' => 'assigned',
                    'assigned_to' => auth()->id(),
                ]);

                $importedCount++;

                if (auth()->user()) {
                    event(new LeadAssigned($lead, auth()->user()));
                }
            }

            // Audit Batch Record Creation
            $filename = $this->file ? $this->file->getClientOriginalName() : 'batch_import.csv';

            $this->completedBatch = UploadBatch::create([
                'uploaded_by' => auth()->id(),
                'project_id' => $this->projectId,
                'campaign_id' => $this->campaignId,
                'lead_source_id' => $this->leadSourceId,
                'filename' => $filename,
                'total_rows' => $totalRows,
                'imported_count' => $importedCount,
                'skipped_count' => $skippedCount,
                'failed_count' => $failedCount,
                'error_log' => $errorLog,
            ]);

            $this->step = 3;
            $this->dispatch('toast', type: 'success', message: "Batch import completed! Imported {$importedCount} leads, skipped {$skippedCount} duplicates, {$failedCount} failed validation.");
        } catch (Throwable $e) {
            $this->dispatch('toast', type: 'error', message: "Import execution error: {$e->getMessage()}");
        }
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

    public function render()
    {
        $projects = Project::all();
        $campaigns = Campaign::all();
        $sources = LeadSource::all();

        return view('livewire.leads.bulk-lead-upload', [
            'projects' => $projects,
            'campaigns' => $campaigns,
            'sources' => $sources,
        ])->layout('layouts.app');
    }
}
