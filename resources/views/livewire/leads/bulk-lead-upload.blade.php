<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-ink">Bulk Lead CSV / Excel Import</h1>
            <p class="text-xs text-muted">Upload multi-lead spreadsheets, map column headers, and run through duplicate protection.</p>
        </div>

        <a href="{{ route('leads.upload-history') }}" class="text-xs font-bold text-primary hover:underline">
            View Upload History
        </a>
    </div>



    <!-- Wizard Progress Indicator -->
    <div class="bg-surface rounded-card border border-border p-4 shadow-sm">
        <div class="flex items-center justify-around text-xs font-bold">
            <div class="flex items-center space-x-2 {{ $step >= 1 ? 'text-primary' : 'text-muted' }}">
                <span class="h-6 w-6 rounded-full flex items-center justify-center border border-current">1</span>
                <span>Upload & Column Mapping</span>
            </div>
            <div class="h-0.5 w-16 bg-border"></div>
            <div class="flex items-center space-x-2 {{ $step >= 2 ? 'text-primary' : 'text-muted' }}">
                <span class="h-6 w-6 rounded-full flex items-center justify-center border border-current">2</span>
                <span>Attribution & Target Project</span>
            </div>
            <div class="h-0.5 w-16 bg-border"></div>
            <div class="flex items-center space-x-2 {{ $step >= 3 ? 'text-primary' : 'text-muted' }}">
                <span class="h-6 w-6 rounded-full flex items-center justify-center border border-current">3</span>
                <span>Import Summary</span>
            </div>
        </div>
    </div>

    <!-- STEP 1: Upload & Mapping -->
    @if($step === 1)
        <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-6">
            <h3 class="text-xs font-bold uppercase text-muted tracking-wider">Step 1: Upload File & Map Column Headers</h3>

            <!-- Drag & Drop Input -->
            <div class="border-2 border-dashed border-border rounded-xl p-8 text-center bg-canvas/40 hover:bg-canvas transition space-y-3">
                <input type="file" wire:model="file" id="fileUploadInput" class="hidden" accept=".csv,.xlsx,.xls,.txt">
                <label for="fileUploadInput" class="cursor-pointer space-y-2 block">
                    <svg class="w-10 h-10 mx-auto text-muted opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                    <span class="text-xs font-bold text-ink block">Click to Browse or Drag CSV / Excel File</span>
                    <span class="text-[11px] text-muted block">Supports .csv, .xlsx, .xls (Max 10MB)</span>
                </label>

                @if($file)
                    <div class="text-xs font-bold text-emerald-600">
                        Selected File: {{ $file->getClientOriginalName() }}
                    </div>
                @endif
            </div>

            <!-- Column Mapping Table & Preview -->
            @if(!empty($headers))
                <div class="space-y-4 pt-4 border-t border-border">
                    <h4 class="text-xs font-bold text-ink">Map Spreadsheet Headers to Lead Fields</h4>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($availableFields as $fieldKey => $fieldLabel)
                            <div class="p-3 bg-canvas rounded-lg border border-border space-y-1.5">
                                <label class="text-xs font-bold text-ink block">{{ $fieldLabel }}</label>
                                <x-ui.themed-select 
                                    wire:model="columnMapping.{{ $fieldKey }}"
                                    :options="array_merge(['' => '-- Unmapped --'], collect($headers)->mapWithKeys(fn($h, $i) => [(string)$i => $h . ' (Col #' . ($i + 1) . ')'])->toArray())"
                                    placeholder="-- Unmapped --"
                                    class="w-full"
                                />
                            </div>
                        @endforeach
                    </div>

                    <!-- First 10 Rows Preview Table -->
                    <div class="pt-4 space-y-2">
                        <h4 class="text-xs font-bold text-muted uppercase">Previewing First 10 Rows</h4>

                        <div class="overflow-x-auto border border-border rounded-lg">
                            <table class="w-full text-left text-xs text-ink border-collapse">
                                <thead>
                                    <tr class="bg-canvas border-b border-border text-[10px] uppercase font-bold text-muted">
                                        @foreach($headers as $h)
                                            <th class="py-2 px-3 whitespace-nowrap">{{ $h }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    @foreach($previewRows as $pRow)
                                        <tr class="hover:bg-canvas/50">
                                            @foreach($pRow as $cell)
                                                <td class="py-2 px-3 whitespace-nowrap font-mono text-[11px] text-muted">{{ $cell }}</td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="flex justify-end pt-4">
                        <x-ui.button wire:click="proceedToAttribution" variant="primary" class="text-xs">
                            Proceed to Step 2: Attribution →
                        </x-ui.button>
                    </div>
                </div>
            @endif
        </div>
    @endif

    <!-- STEP 2: Target Attribution -->
    @if($step === 2)
        <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-6">
            <h3 class="text-xs font-bold uppercase text-muted tracking-wider">Step 2: Assign Project & Source Attribution</h3>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="space-y-1">
                    <label class="text-xs font-bold text-ink">Target Project (Required) *</label>
                    <livewire:shared.searchable-select 
                        :model="\App\Models\Project::class"
                        placeholder="-- Select Target Project --"
                        wire:model="projectId"
                        key="upload-project"
                    />
                </div>

                <div class="space-y-1">
                    <label class="text-xs font-bold text-ink">Campaign (Optional)</label>
                    <livewire:shared.searchable-select 
                        :model="\App\Models\Campaign::class"
                        placeholder="-- Select Campaign --"
                        wire:model="campaignId"
                        key="upload-campaign"
                    />
                </div>

                <div class="space-y-1">
                    <label class="text-xs font-bold text-ink">Lead Source (Optional)</label>
                    <livewire:shared.searchable-select 
                        :model="\App\Models\LeadSource::class"
                        placeholder="-- Select Lead Source --"
                        wire:model="leadSourceId"
                        key="upload-source"
                    />
                </div>
            </div>

            <div class="flex items-center justify-between pt-4 border-t border-border">
                <x-ui.button wire:click="$set('step', 1)" variant="secondary" class="text-xs">
                    ← Back to Mapping
                </x-ui.button>

                <x-ui.button wire:click="processBatch" variant="primary" class="text-xs">
                    Run Batch Import
                </x-ui.button>
            </div>
        </div>
    @endif

    <!-- STEP 3: Summary -->
    @if($step === 3 && $completedBatch)
        <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-6">
            <div class="flex items-center space-x-3 text-emerald-600">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <div>
                    <h3 class="text-lg font-bold text-ink">Import Completed Successfully</h3>
                    <p class="text-xs text-muted">Batch archive #{{ $completedBatch->id }} logged for audit compliance.</p>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="p-4 bg-canvas rounded-lg border border-border text-center">
                    <span class="text-[10px] font-bold uppercase text-muted block">Total Rows Processed</span>
                    <span class="text-xl font-bold font-mono text-ink">{{ $completedBatch->total_rows }}</span>
                </div>

                <div class="p-4 bg-emerald-50 rounded-lg border border-emerald-200 text-center">
                    <span class="text-[10px] font-bold uppercase text-emerald-700 block">Leads Imported</span>
                    <span class="text-xl font-bold font-mono text-emerald-800">{{ $completedBatch->imported_count }}</span>
                </div>

                <div class="p-4 bg-amber-50 rounded-lg border border-amber-200 text-center">
                    <span class="text-[10px] font-bold uppercase text-amber-700 block">Skipped (Duplicates)</span>
                    <span class="text-xl font-bold font-mono text-amber-800">{{ $completedBatch->skipped_count }}</span>
                </div>

                <div class="p-4 bg-red-50 rounded-lg border border-red-200 text-center">
                    <span class="text-[10px] font-bold uppercase text-red-700 block">Failed Validation</span>
                    <span class="text-xl font-bold font-mono text-red-800">{{ $completedBatch->failed_count }}</span>
                </div>
            </div>

            @if($completedBatch->failed_count > 0)
                <div class="p-4 bg-red-50 rounded-lg border border-red-200 flex items-center justify-between">
                    <span class="text-xs font-bold text-red-700">
                        {{ $completedBatch->failed_count }} rows failed validation. Download the Error CSV to review detailed failure reasons.
                    </span>
                    <x-ui.button wire:click="downloadErrorCsv({{ $completedBatch->id }})" variant="danger" class="text-xs">
                        Download Error CSV
                    </x-ui.button>
                </div>
            @endif

            <div class="flex justify-end pt-2">
                <a href="{{ route('leads.kanban') }}" class="px-4 py-2 bg-ink text-white text-xs font-bold rounded-lg hover:bg-black transition">
                    View Leads in Kanban →
                </a>
            </div>
        </div>
    @endif
</div>
