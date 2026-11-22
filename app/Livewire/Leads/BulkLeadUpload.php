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

    #[\Livewire\Attributes\Locked]
    public ?int $organizationId = null;

    public function boot(): void
    {
        if ($this->organizationId) abort_unless(auth()->user()?->hasAnyRole(['Super Admin', 'super-admin']), 403);
    }

    public $file = null;
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

    public function mount(?int $organizationId = null): void
    {
        if ($organizationId) {
            abort_unless(auth()->user()?->hasAnyRole(['Super Admin', 'super-admin']), 403);
            $this->organizationId = \App\Models\Organization::findOrFail($organizationId)->id;
        }
        $this->resetMappingDefaults();
    }

    protected function resetMappingDefaults(): void
    {
        foreach ($this->availableFields as $fieldKey => $label) {
            if (!isset($this->columnMapping[$fieldKey])) {
                $this->columnMapping[$fieldKey] = '';
            }
        }
    }

    public function updatedFile(): void
    {
        $this->resetMappingDefaults();
        $this->headers = $this->previewRows = $this->allCsvRows = [];

        $this->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
        ]);

        $localFile = null;
        try {
            // Read through Livewire's storage API, then use a short local path for ZIP readers.
            // This also supports remote temporary-upload disks and long Windows filenames.
            $localFile = tmpfile();
            $input = method_exists($this->file, 'readStream') ? $this->file->readStream() : fopen($this->file->getRealPath(), 'rb');
            if (!$localFile || !$input) throw new \RuntimeException('Cannot read the uploaded file.');
            stream_copy_to_stream($input, $localFile);
            fclose($input);
            $path = stream_get_meta_data($localFile)['uri'];
            $extension = strtolower($this->file->getClientOriginalExtension());
            $rows = [];

            if (in_array($extension, ['xlsx', 'xls'])) {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
                $worksheet = $spreadsheet->getActiveSheet();
                $rawRows = $worksheet->toArray();

                foreach ($rawRows as $r) {
                    if (is_array($r) && !empty(array_filter($r, fn($v) => $v !== null && trim((string)$v) !== ''))) {
                        $rows[] = array_map(fn($v) => mb_convert_encoding(trim((string)($v ?? '')), 'UTF-8', 'UTF-8'), $r);
                    }
                }
            } else {
                $handle = fopen($path, 'r');
                if ($handle !== false) {
                    while (($r = fgetcsv($handle)) !== false) {
                        if (is_array($r) && !empty(array_filter($r, fn($v) => $v !== null && trim((string)$v) !== ''))) {
                            $rows[] = array_map(fn($v) => mb_convert_encoding(trim((string)($v ?? '')), 'UTF-8', 'UTF-8'), $r);
                        }
                    }
                    fclose($handle);
                }
            }

            if (!empty($rows)) {
                $this->headers = [];
                $this->previewRows = [];
                $this->allCsvRows = [];

                // Detect actual header row (handling BaseStyledExport masthead banners)
                $headerRowIndex = 0;
                foreach ($rows as $idx => $r) {
                    $rowText = strtolower(implode(' ', $r));
                    if (
                        str_contains($rowText, 'mobile') || 
                        str_contains($rowText, 'phone') || 
                        str_contains($rowText, 'email') || 
                        str_contains($rowText, 'name')
                    ) {
                        $headerRowIndex = $idx;
                        break;
                    }
                }

                $headerRow = $rows[$headerRowIndex] ?? [];
                foreach ($headerRow as $index => $col) {
                    $this->headers[$index] = trim($col);
                }

                $this->allCsvRows = array_slice($rows, $headerRowIndex + 1);
                $this->previewRows = array_slice($this->allCsvRows, 0, 10);
                $this->autoGuessColumnMapping();
            }
        } catch (Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Failed to parse file: ' . $e->getMessage());
        } finally {
            if (is_resource($localFile)) fclose($localFile);
        }
    }

    protected function autoGuessColumnMapping(): void
    {
        $this->resetMappingDefaults();

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
            } elseif (in_array($normalized, ['budget', 'price', 'amount', 'investment', 'budgetinr'])) {
                $this->columnMapping['budget'] = (string) $index;
            } elseif (in_array($normalized, ['propertytype', 'property', 'unittype', 'configuration', 'type'])) {
                $this->columnMapping['property_type'] = (string) $index;
            } elseif (in_array($normalized, ['requirement', 'notes', 'description', 'remarks', 'comments', 'requirementnotes'])) {
                $this->columnMapping['requirement'] = (string) $index;
            }
        }
    }

    public function proceedToAttribution(): void
    {
        if (!isset($this->columnMapping['mobile']) || $this->columnMapping['mobile'] === '') {
            $this->dispatch('toast', type: 'error', message: 'Please map a column to Mobile Number (Required).');
            return;
        }

        $this->step = 2;
    }

    public function processBatch(): void
    {
        $this->validate([
            'projectId' => 'required|exists:projects,id',
            'campaignId' => ['nullable', \Illuminate\Validation\Rule::exists('campaigns', 'id')->where('project_id', $this->projectId)],
            'leadSourceId' => 'nullable|exists:lead_sources,id',
            'columnMapping.mobile' => 'required|integer|min:0',
        ]);

        if ($this->organizationId) {
            abort_unless(Project::whereHas('client', fn ($q) => $q->where('organization_id', $this->organizationId))->whereKey($this->projectId)->exists(), 403);
            $this->validate(['leadSourceId' => ['nullable', \Illuminate\Validation\Rule::in($this->attributionSources()->pluck('id')->all())]]);
        }

        try {
            \Illuminate\Support\Facades\DB::transaction(function () {
            $project = Project::whereKey($this->projectId)->lockForUpdate()->firstOrFail();

            if (empty($this->allCsvRows) && $this->file) {
                $this->updatedFile();
            }
            if (empty($this->allCsvRows)) throw new \RuntimeException('No data rows were found. Upload a valid file before importing.');

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
                    if ($colIdx !== '' && isset($row[$colIdx])) {
                        $rowData[$field] = trim($row[$colIdx]);
                    }
                }

                $mobile = $rowData['mobile'] ?? '';
                // Clean mobile string
                $cleanMobile = preg_replace('/[^0-9]/', '', $mobile);

                // Row Validation
                if (empty($cleanMobile) || strlen($cleanMobile) < 10 || strlen($cleanMobile) > 15) {
                    $failedCount++;
                    $errorLog[] = [
                        'row' => $totalRows,
                        'data' => implode(', ', $row),
                        'reason' => 'Invalid or missing mobile number format.',
                    ];
                    continue;
                }

                $formattedMobile = (strlen($cleanMobile) === 10) ? '+91' . $cleanMobile : '+' . $cleanMobile;

                $validation = \Illuminate\Support\Facades\Validator::make($rowData, [
                    'name' => 'nullable|string|max:255', 'email' => 'nullable|email|max:255',
                    'city' => 'nullable|string|max:255', 'property_type' => 'nullable|string|max:255',
                ]);
                if ($validation->fails()) {
                    $failedCount++;
                    $errorLog[] = ['row' => $totalRows, 'data' => implode(', ', $row), 'reason' => implode(' ', $validation->errors()->all())];
                    continue;
                }

                // 90-Day Duplicate Check Pipeline
                $isDuplicate = Lead::where('client_id', $project->client_id)
                    ->where(function ($q) use ($formattedMobile, $cleanMobile, $rowData) {
                        $q->whereIn('mobile', [$formattedMobile, $cleanMobile, substr($cleanMobile, -10)]);
                        if (!empty($rowData['email'])) $q->orWhere('email', $rowData['email']);
                    })
                    ->where('created_at', '>=', now()->subDays(90))
                    ->exists();

                if ($isDuplicate) {
                    $skippedCount++;
                    continue;
                }

                // Create Inbound Lead
                $effectiveSourceId = $this->leadSourceId ?? LeadSource::firstOrCreate(['name' => 'csv_import'])->id;
                $effectiveCampaignId = $this->campaignId ?: Campaign::where('project_id', $this->projectId)->value('id');
                if (!$effectiveCampaignId) $effectiveCampaignId = Campaign::firstOrCreate(['project_id' => $project->id, 'name' => 'General Campaign'], ['lead_source_id' => $effectiveSourceId, 'status' => 'active'])->id;

                $lead = Lead::create([
                    'lead_code' => Lead::generateUniqueLeadCode(),
                    'client_id' => $project->client_id,
                    'campaign_id' => $effectiveCampaignId,
                    'lead_source_id' => $effectiveSourceId,
                    'name' => $rowData['name'] ?? 'CSV Imported Lead',
                    'mobile' => $formattedMobile,
                    'email' => $rowData['email'] ?? null,
                    'city' => $rowData['city'] ?? null,
                    'budget' => !empty($rowData['budget']) ? (float) preg_replace('/[^0-9.]/', '', $rowData['budget']) : null,
                    'property_type' => $rowData['property_type'] ?? null,
                    'requirement' => $rowData['requirement'] ?? null,
                    'status' => $this->organizationId ? 'new' : 'assigned',
                    'current_stage' => $this->organizationId ? 'new' : 'assigned',
                    'assigned_to' => $this->organizationId ? null : auth()->id(),
                ]);

                $importedCount++;

                if ($this->organizationId) {
                    event(new \App\Events\LeadCreated($lead));
                } elseif (auth()->user()) {
                    event(new LeadAssigned($lead, auth()->user()));
                }
            }

            // Audit Batch Record Creation & Permanent File Storage
            $originalFilename = $this->file ? $this->file->getClientOriginalName() : 'batch_import.csv';
            $storedPath = null;
            if ($this->file) {
                try {
                    $storedPath = $this->file->store('imports/batches/' . date('Y/m'), 'local');
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Failed to store bulk upload original file: ' . $e->getMessage());
                }
            }

            $this->completedBatch = UploadBatch::create([
                'uploaded_by' => auth()->id(),
                'project_id' => $this->projectId,
                'campaign_id' => $this->campaignId,
                'lead_source_id' => $this->leadSourceId,
                'filename' => $originalFilename,
                'original_filename' => $originalFilename,
                'file_path' => $storedPath,
                'total_rows' => $totalRows,
                'imported_count' => $importedCount,
                'skipped_count' => $skippedCount,
                'failed_count' => $failedCount,
                'error_log' => $errorLog,
            ]);

            $this->step = 3;
            $this->dispatch('lead-updated');
            $this->dispatch('bulk-import-completed', batchId: $this->completedBatch->id);
            $this->dispatch('toast', type: 'success', message: "Batch import completed! Imported {$importedCount} leads, skipped {$skippedCount} duplicates, {$failedCount} failed validation.");
            });
        } catch (Throwable $e) {
            $this->dispatch('toast', type: 'error', message: "Import execution error: {$e->getMessage()}");
        }
    }

    public function finishAndClose(): void
    {
        $this->dispatch('close-client-import');
        $this->dispatch('lead-updated');
    }

    public function downloadErrorCsv(int $batchId): StreamedResponse
    {
        $batch = UploadBatch::findOrFail($batchId);
        if ($this->organizationId) abort_unless(Project::whereHas('client', fn ($q) => $q->where('organization_id', $this->organizationId))->whereKey($batch->project_id)->exists(), 403);
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

    public function downloadTemplate(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\LeadImportTemplateExport(), 'Lead_Import_Template.xlsx');
    }

    public function updatedProjectId(): void
    {
        $this->campaignId = null;
        $this->leadSourceId = null;
    }

    public function updatedCampaignId(): void { $this->leadSourceId = null; }

    protected function attributionProjects()
    {
        return Project::when($this->organizationId, fn ($q) => $q->whereHas('client', fn ($owner) => $owner->where('organization_id', $this->organizationId)));
    }

    protected function attributionCampaigns()
    {
        return Campaign::whereIn('project_id', $this->attributionProjects()->select('id'))
            ->when($this->projectId, fn ($q) => $q->where('project_id', $this->projectId));
    }

    protected function attributionSources()
    {
        if (!$this->organizationId) return LeadSource::query();
        $campaigns = $this->attributionCampaigns()->when($this->campaignId, fn ($q) => $q->whereKey($this->campaignId));
        $campaignSourceIds = (clone $campaigns)->pluck('lead_source_id');
        $leadSourceIds = Lead::whereIn('campaign_id', (clone $campaigns)->select('id'))
            ->whereHas('client', fn ($q) => $q->where('organization_id', $this->organizationId))->pluck('lead_source_id');
        return LeadSource::whereIn('id', $campaignSourceIds->merge($leadSourceIds)->filter()->unique());
    }

    public function render()
    {
        $projects = $this->attributionProjects()->orderBy('name')->get();
        $campaigns = $this->attributionCampaigns()->orderBy('name')->get();
        $sources = $this->attributionSources()->orderBy('name')->get();

        return view('livewire.leads.bulk-lead-upload', [
            'projects' => $projects,
            'campaigns' => $campaigns,
            'sources' => $sources,
        ])->layout('layouts.app');
    }
}
