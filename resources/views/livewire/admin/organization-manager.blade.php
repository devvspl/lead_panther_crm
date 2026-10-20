<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2 text-xs text-muted mb-1">
                <span>System Administration</span>
                <span>/</span>
                <span class="text-ink font-semibold">Organizations</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-ink flex items-center gap-2.5">
                <svg class="w-6 h-6 text-primary flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
                <span>Organizations Management</span>
            </h1>
            <p class="text-xs text-muted">Manage Real Estate Builder, Channel Partner, and Platform multi-tenant organizations.</p>
        </div>
    </div>

    <!-- Register Organization Modal (Create & Import) -->
    <x-modal name="register-organization-modal" :show="$showCreateOrgModal" maxWidth="3xl">
        <div class="p-5 sm:p-6 space-y-5">
            <!-- Modal Header -->
            <div class="flex items-center justify-between pb-3.5 border-b border-border">
                <div class="flex items-center gap-3">
                    <div class="p-2 rounded-xl bg-primary/10 text-primary border border-primary/20">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-ink">Register Organization</h3>
                        <p class="text-xs text-muted">Create a single organization manually or batch import organizations via spreadsheet.</p>
                    </div>
                </div>
                <button 
                    type="button" 
                    wire:click="closeRegisterOrgModal" 
                    class="text-muted hover:text-ink transition p-1.5 rounded-lg hover:bg-canvas cursor-pointer"
                    title="Close Modal"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Two Options: Create vs Import Tabs -->
            <div class="grid grid-cols-2 gap-1.5 p-1 bg-canvas rounded-xl border border-border">
                <button 
                    type="button" 
                    wire:click="$set('orgModalTab', 'create')"
                    class="flex items-center justify-center gap-2 py-2 px-3 rounded-lg text-xs font-bold transition cursor-pointer {{ $orgModalTab === 'create' ? 'bg-surface text-ink shadow-xs border border-border' : 'text-muted hover:text-ink' }}"
                >
                    <svg class="w-4 h-4 {{ $orgModalTab === 'create' ? 'text-primary' : 'text-muted' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    <span>Create Organization</span>
                </button>

                <button 
                    type="button" 
                    wire:click="$set('orgModalTab', 'import')"
                    class="flex items-center justify-center gap-2 py-2 px-3 rounded-lg text-xs font-bold transition cursor-pointer {{ $orgModalTab === 'import' ? 'bg-surface text-ink shadow-xs border border-border' : 'text-muted hover:text-ink' }}"
                >
                    <svg class="w-4 h-4 {{ $orgModalTab === 'import' ? 'text-primary' : 'text-muted' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    <span>Import Organizations</span>
                </button>
            </div>

            <!-- OPTION 1: CREATE ORGANIZATION -->
            @if($orgModalTab === 'create')
                <div class="space-y-4 pt-1">
                    <div class="space-y-3.5 text-xs">
                        <div>
                            <label class="font-bold text-ink">Organization Name <span class="text-red-500">*</span></label>
                            <input 
                                type="text" 
                                wire:model="name" 
                                placeholder="e.g. Prestige Developers Ltd"
                                class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:ring-2 focus:ring-primary/20 focus:border-primary transition mt-1"
                            >
                            @error('name') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="font-bold text-ink">Initial Status</label>
                            <x-ui.themed-select 
                                wire:model="status" 
                                :options="['active' => 'Active', 'suspended' => 'Suspended']"
                                placeholder="Initial Status" 
                                class="w-full mt-1" 
                            />
                            @error('status') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-border">
                        <button 
                            type="button" 
                            wire:click="closeRegisterOrgModal" 
                            class="px-4 py-2 border border-border bg-surface text-ink text-xs font-semibold rounded-lg hover:bg-canvas transition cursor-pointer"
                        >
                            Cancel
                        </button>
                        <x-ui.button wire:click="createOrganization" variant="primary" class="text-xs">
                            Save Organization
                        </x-ui.button>
                    </div>
                </div>
            @endif

            <!-- OPTION 2: IMPORT ORGANIZATIONS -->
            @if($orgModalTab === 'import')
                <div class="space-y-4 pt-1 text-xs">
                    <!-- Template Download Bar -->
                    <div class="p-3 bg-canvas/60 border border-border rounded-xl flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <span class="p-1.5 rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </span>
                            <div>
                                <div class="font-bold text-ink">Download Import Template</div>
                                <div class="text-[11px] text-muted">Includes required column layout, field guide, and sample rows.</div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <button 
                                type="button" 
                                wire:click="downloadImportTemplate" 
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-surface border border-border text-ink text-xs font-semibold hover:border-ink transition shadow-2xs cursor-pointer"
                            >
                                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                </svg>
                                <span>Excel (.xlsx)</span>
                            </button>

                            <button 
                                type="button" 
                                wire:click="downloadImportTemplateCsv" 
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-surface border border-border text-ink text-xs font-semibold hover:border-ink transition shadow-2xs cursor-pointer"
                            >
                                <svg class="w-3.5 h-3.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                </svg>
                                <span>CSV (.csv)</span>
                            </button>
                        </div>
                    </div>

                    <!-- Required Format & Sample Data Table -->
                    <div class="border border-border rounded-xl overflow-hidden bg-surface">
                        <div class="px-3.5 py-2.5 bg-canvas/70 border-b border-border flex items-center justify-between">
                            <span class="font-bold text-[11px] text-ink uppercase tracking-wider flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <span>Required Columns &amp; Sample Format</span>
                            </span>
                            <span class="text-[10px] text-muted">UTF-8 Encoded</span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-[11px] border-collapse">
                                <thead>
                                    <tr class="bg-canvas/30 text-muted border-b border-border">
                                        <th class="py-2 px-3 font-semibold">
                                            Organization Name <span class="text-red-500 font-bold">*</span>
                                        </th>
                                        <th class="py-2 px-3 font-semibold">Status <span class="text-muted font-normal">(Optional)</span></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border font-mono text-[11px]">
                                    <tr class="hover:bg-canvas/40">
                                        <td class="py-1.5 px-3 text-ink font-sans font-medium">Prestige Estates Ltd</td>
                                        <td class="py-1.5 px-3 text-emerald-700">active</td>
                                    </tr>
                                    <tr class="hover:bg-canvas/40">
                                        <td class="py-1.5 px-3 text-ink font-sans font-medium">Apex Realty Advisors</td>
                                        <td class="py-1.5 px-3 text-emerald-700">active</td>
                                    </tr>
                                    <tr class="hover:bg-canvas/40">
                                        <td class="py-1.5 px-3 text-ink font-sans font-medium">Platform Administration</td>
                                        <td class="py-1.5 px-3 text-emerald-700">active</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="px-3.5 py-2 bg-canvas/40 border-t border-border text-[10px] text-muted flex items-center justify-between">
                            <span>* Organization Name must be unique and non-empty</span>
                            <span>Status defaults to <code class="text-ink font-bold">active</code> (allowed: <code class="text-ink font-bold">active</code>, <code class="text-ink font-bold">suspended</code>)</span>
                        </div>
                    </div>

                    <!-- File Upload Input Area -->
                    <div class="space-y-2">
                        <label class="font-bold text-ink block">Upload File (Excel or CSV)</label>

                        <div 
                            x-data="{ isDragging: false }"
                            @dragover.prevent="isDragging = true"
                            @dragleave.prevent="isDragging = false"
                            @drop.prevent="isDragging = false"
                            class="relative border-2 border-dashed rounded-xl p-5 text-center transition cursor-pointer"
                            :class="isDragging ? 'border-primary bg-primary/5' : 'border-border bg-canvas/30 hover:border-ink/30 hover:bg-canvas/60'"
                        >
                            <input 
                                type="file" 
                                wire:model="importFile" 
                                accept=".csv,.txt,.xlsx,.xls"
                                class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                            >

                            <div class="flex flex-col items-center justify-center gap-1.5 pointer-events-none">
                                <div class="p-2.5 rounded-full bg-primary/10 text-primary">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                    </svg>
                                </div>
                                <div class="font-bold text-ink">
                                    Click to select or drag &amp; drop file here
                                </div>
                                <p class="text-[11px] text-muted">
                                    Supports Microsoft Excel (.xlsx, .xls) and Comma-Separated Values (.csv) up to 10MB
                                </p>
                            </div>
                        </div>

                        <div wire:loading wire:target="importFile" class="text-primary text-xs font-semibold flex items-center gap-2 pt-1">
                            <svg class="animate-spin w-4 h-4 text-primary" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>Uploading and parsing file...</span>
                        </div>

                        @error('importFile')
                            <span class="text-red-500 text-[10px] block mt-1">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- File Details / Selected File Badge -->
                    @if($importFile)
                        <div class="p-3 bg-surface border border-border rounded-xl flex items-center justify-between gap-3 shadow-2xs">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <div class="p-1.5 rounded-lg bg-primary/10 text-primary shrink-0">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                </div>
                                <div class="truncate">
                                    <span class="font-bold text-ink truncate block">{{ $importFile->getClientOriginalName() }}</span>
                                    <span class="text-[10px] text-muted">{{ number_format($importFile->getSize() / 1024, 1) }} KB</span>
                                </div>
                            </div>
                            <button 
                                type="button" 
                                wire:click="resetImportState" 
                                class="text-red-600 hover:text-red-700 text-xs font-semibold px-2.5 py-1 rounded-lg hover:bg-red-50 transition cursor-pointer shrink-0"
                            >
                                Remove File
                            </button>
                        </div>
                    @endif

                    <!-- Validation Summary & Row-wise Errors -->
                    @if(!empty($importErrors))
                        <div class="space-y-2.5">
                            <div class="p-3 bg-red-50 border border-red-200 rounded-xl text-red-900 flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-red-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                    </svg>
                                    <div>
                                        <span class="font-bold">Row Validation Errors ({{ count($importErrors) }} row(s) failed)</span>
                                        <p class="text-[11px] text-red-800">Please review row errors below or download the error report.</p>
                                    </div>
                                </div>
                                <button 
                                    type="button" 
                                    wire:click="downloadImportErrorsCsv" 
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-white border border-red-300 text-red-800 rounded-lg text-xs font-bold hover:bg-red-100 transition shadow-2xs shrink-0 cursor-pointer"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                    </svg>
                                    <span>Download Error CSV</span>
                                </button>
                            </div>

                            <!-- Row-wise Error Table -->
                            <div class="border border-red-200 rounded-xl overflow-hidden max-h-48 overflow-y-auto bg-surface">
                                <table class="w-full text-left text-[11px] border-collapse">
                                    <thead class="sticky top-0 bg-red-50 text-red-900 border-b border-red-200">
                                        <tr>
                                            <th class="py-1.5 px-3 font-bold w-16">Row #</th>
                                            <th class="py-1.5 px-3 font-bold">Organization</th>
                                            <th class="py-1.5 px-3 font-bold">Failure Reason</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-red-100">
                                        @foreach($importErrors as $err)
                                            <tr class="hover:bg-red-50/50">
                                                <td class="py-1.5 px-3 font-mono font-bold text-red-700">#{{ $err['row'] }}</td>
                                                <td class="py-1.5 px-3 font-medium text-ink truncate max-w-[150px]">{{ $err['name'] }}</td>
                                                <td class="py-1.5 px-3 text-red-700 font-medium">
                                                    {{ implode('; ', $err['errors']) }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    <!-- Valid Rows Preview -->
                    @if(!empty($importRows))
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-ink flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    <span>Valid Organizations Ready to Import ({{ count($importRows) }})</span>
                                </span>
                                @if(count($importRows) > 5)
                                    <span class="text-[10px] text-muted">Showing first 5 of {{ count($importRows) }} records</span>
                                @endif
                            </div>

                            <div class="border border-border rounded-xl overflow-hidden bg-surface">
                                <table class="w-full text-left text-[11px] border-collapse">
                                    <thead class="bg-canvas/50 text-muted border-b border-border">
                                        <tr>
                                            <th class="py-1.5 px-3 font-semibold">Row #</th>
                                            <th class="py-1.5 px-3 font-semibold">Organization Name</th>
                                            <th class="py-1.5 px-3 font-semibold">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border">
                                        @foreach(array_slice($importRows, 0, 5) as $row)
                                            <tr class="hover:bg-canvas/30">
                                                <td class="py-1.5 px-3 font-mono text-muted">#{{ $row['row'] }}</td>
                                                <td class="py-1.5 px-3 font-bold text-ink">{{ $row['name'] }}</td>
                                                <td class="py-1.5 px-3">
                                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold font-mono {{ $row['status'] === 'active' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200' }}">
                                                        {{ $row['status'] }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    <!-- Import Actions Footer -->
                    <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-border">
                        <button 
                            type="button" 
                            wire:click="closeRegisterOrgModal" 
                            class="px-4 py-2 border border-border bg-surface text-ink text-xs font-semibold rounded-lg hover:bg-canvas transition cursor-pointer"
                        >
                            Cancel
                        </button>
                        <button 
                            type="button" 
                            wire:click="processImport"
                            @if(empty($importRows)) disabled @endif
                            class="inline-flex items-center gap-1.5 px-4 py-2 bg-ink text-white text-xs font-bold rounded-lg hover:bg-neutral-800 transition shadow-xs cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                        >
                            <svg wire:loading.remove wire:target="processImport" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                            </svg>
                            <svg wire:loading wire:target="processImport" class="animate-spin w-3.5 h-3.5 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span wire:loading.remove wire:target="processImport">
                                Confirm &amp; Import {{ !empty($importRows) ? '(' . count($importRows) . ')' : '' }}
                            </span>
                            <span wire:loading wire:target="processImport">Importing...</span>
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </x-modal>

    <!-- Advanced Table Component -->
    <x-ui.advanced-table 
        :columns="$this->tableColumns()"
        :rows="$organizations"
        :quickFilters="$this->quickFilters()"
        :activeStatus="$statusFilter"
        :visibleColumns="$visibleColumns"
        :sortField="$sortField"
        :sortDirection="$sortDirection"
        :filterCount="$this->activeFilterCount"
        searchPlaceholder="Search organization name..."
        emptyTitle="No Organizations Found"
        emptyMessage="No organizations match your current search and filters."
    >
        <!-- Primary Action Slot -->
        <x-slot:action>
            <div class="flex items-center gap-2">
                <x-ui.export-button target="exportExcel" class="text-xs" />
                <button 
                    type="button" 
                    wire:click="openRegisterOrgModal('create')"
                    class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-ink text-white text-xs font-semibold rounded-lg hover:bg-neutral-800 transition shadow-xs cursor-pointer"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    <span>Register Organization</span>
                </button>
            </div>
        </x-slot:action>
    </x-ui.advanced-table>

    <!-- Reusable Organization Details Modal (View, Edit & Save) -->
    <x-modal name="organization-details-modal" :show="$showOrgDetailsModal" maxWidth="2xl">
        <div class="p-5 sm:p-6 space-y-5">
            <!-- Modal Header -->
            <div class="flex items-center justify-between pb-4 border-b border-border">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 rounded-xl bg-primary/10 text-primary border border-primary/20">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-bold text-ink">
                                {{ $editingOrg ? $editingOrg->name : 'Organization Details' }}
                            </h3>
                            @if($editingOrg)
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold bg-canvas text-muted border border-border">
                                    #ORG-{{ str_pad($editingOrg->id, 4, '0', STR_PAD_LEFT) }}
                                </span>
                            @endif
                        </div>
                        <p class="text-xs text-muted">
                            {{ $orgDetailsMode === 'edit' ? 'Update organization information and operational status.' : 'View organization profile, capacity, and management details.' }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <!-- Tab toggle buttons between View and Edit -->
                    <div class="inline-flex p-0.5 rounded-lg bg-canvas border border-border text-xs">
                        <button 
                            type="button" 
                            wire:click="switchOrgDetailsMode('view')" 
                            class="px-2.5 py-1 rounded-md font-semibold transition cursor-pointer {{ $orgDetailsMode === 'view' ? 'bg-surface text-ink shadow-2xs font-bold' : 'text-muted hover:text-ink' }}"
                        >
                            View
                        </button>
                        <button 
                            type="button" 
                            wire:click="switchOrgDetailsMode('edit')" 
                            class="px-2.5 py-1 rounded-md font-semibold transition cursor-pointer {{ $orgDetailsMode === 'edit' ? 'bg-surface text-ink shadow-2xs font-bold' : 'text-muted hover:text-ink' }}"
                        >
                            Edit
                        </button>
                    </div>

                    <button 
                        type="button" 
                        wire:click="closeOrgDetailsModal" 
                        class="text-muted hover:text-ink transition p-1.5 rounded-lg hover:bg-canvas cursor-pointer"
                        title="Close Modal"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>

            @if($editingOrg)
                @if($orgDetailsMode === 'view')
                    <!-- VIEW MODE -->
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                            <div class="p-3.5 rounded-xl bg-canvas/50 border border-border space-y-1">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-muted">Organization Name</span>
                                <div class="text-sm font-bold text-ink">{{ $editingOrg->name }}</div>
                            </div>

                            <div class="p-3.5 rounded-xl bg-canvas/50 border border-border space-y-1">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-muted">Current Status</span>
                                <div>
                                    @if($editingOrg->status === 'active')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                            Active
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-red-50 text-red-700 border border-red-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                                            Suspended
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <div class="p-3.5 rounded-xl bg-canvas/50 border border-border space-y-1">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-muted">Assigned Team Members</span>
                                <div class="flex items-center justify-between">
                                    <span class="text-lg font-bold font-mono text-ink">{{ $editingOrg->users_count ?? 0 }}</span>
                                    <button 
                                        type="button" 
                                        wire:click="openUserModal({{ $editingOrg->id }})" 
                                        class="inline-flex items-center gap-1 text-[11px] font-semibold text-primary hover:underline cursor-pointer"
                                    >
                                        <span>Manage Users</span>
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    </button>
                                </div>
                            </div>

                            <div class="p-3.5 rounded-xl bg-canvas/50 border border-border space-y-1">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-muted">Registered Clients</span>
                                <div class="text-lg font-bold font-mono text-ink">{{ $editingOrg->clients_count ?? 0 }}</div>
                            </div>

                            <div class="p-3.5 rounded-xl bg-canvas/50 border border-border space-y-1">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-muted">Created Date</span>
                                <div class="text-xs font-mono text-ink">{{ $editingOrg->created_at ? $editingOrg->created_at->format('M d, Y h:i A') : '—' }}</div>
                            </div>

                            <div class="p-3.5 rounded-xl bg-canvas/50 border border-border space-y-1">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-muted">Last Updated</span>
                                <div class="text-xs font-mono text-ink">{{ $editingOrg->updated_at ? $editingOrg->updated_at->format('M d, Y h:i A') : '—' }}</div>
                            </div>
                        </div>

                        <!-- Footer Actions -->
                        <div class="flex items-center justify-between pt-4 border-t border-border">
                            <button 
                                type="button" 
                                wire:click="openUserModal({{ $editingOrg->id }})" 
                                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg border border-border bg-surface hover:bg-canvas text-ink text-xs font-semibold transition cursor-pointer shadow-2xs"
                            >
                                <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                                </svg>
                                <span>Manage Users</span>
                            </button>

                            <div class="flex items-center gap-2">
                                <button 
                                    type="button" 
                                    wire:click="closeOrgDetailsModal" 
                                    class="px-4 py-2 border border-border bg-surface text-ink text-xs font-semibold rounded-lg hover:bg-canvas transition cursor-pointer"
                                >
                                    Close
                                </button>
                                <button 
                                    type="button" 
                                    wire:click="switchOrgDetailsMode('edit')" 
                                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-ink text-white text-xs font-semibold rounded-lg hover:bg-neutral-800 transition cursor-pointer shadow-xs"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                    </svg>
                                    <span>Edit Organization</span>
                                </button>
                            </div>
                        </div>
                    </div>
                @else
                    <!-- EDIT MODE -->
                    <form wire:submit.prevent="updateOrganization" class="space-y-4">
                        <div class="space-y-3.5 text-xs">
                            <div>
                                <label class="font-bold text-ink">Organization Name <span class="text-red-500">*</span></label>
                                <input 
                                    type="text" 
                                    wire:model="editName" 
                                    placeholder="e.g. Prestige Estates Projects Ltd"
                                    class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs focus:ring-2 focus:ring-primary/20 focus:border-primary transition mt-1"
                                >
                                @error('editName') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="font-bold text-ink">Organization Status <span class="text-red-500">*</span></label>
                                <x-ui.themed-select 
                                    wire:model="editStatus" 
                                    :options="['active' => 'Active', 'suspended' => 'Suspended']"
                                    placeholder="Select Status" 
                                    class="w-full mt-1" 
                                />
                                @error('editStatus') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <!-- Footer Actions -->
                        <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-border">
                            <button 
                                type="button" 
                                wire:click="switchOrgDetailsMode('view')" 
                                class="px-4 py-2 border border-border bg-surface text-ink text-xs font-semibold rounded-lg hover:bg-canvas transition cursor-pointer"
                            >
                                Cancel
                            </button>
                            <x-ui.button type="submit" variant="primary" class="text-xs">
                                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Save Changes
                            </x-ui.button>
                        </div>
                    </form>
                @endif
            @endif
        </div>
    </x-modal>

    <!-- Organization User Management Modal -->
    <x-modal name="manage-users-modal" :show="$showUserModal" maxWidth="3xl">
        <div class="flex flex-col max-h-[85vh]">
            <!-- Modal Header -->
            <div class="px-5 sm:px-6 py-4 border-b border-border flex items-center justify-between shrink-0 bg-surface">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="p-2 rounded-xl bg-primary/10 text-primary border border-primary/20 shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-bold text-ink truncate">
                                Manage Users — {{ $selectedOrg ? $selectedOrg->name : 'Organization' }}
                            </h3>
                            @if($selectedOrg)
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase shrink-0 {{ $selectedOrg->status === 'active' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200' }}">
                                    {{ $selectedOrg->status }}
                                </span>
                            @endif
                        </div>
                        <p class="text-xs text-muted truncate">
                            Register new team members or manage role permissions for this organization.
                        </p>
                    </div>
                </div>

                <button 
                    type="button" 
                    wire:click="closeUserModal" 
                    class="text-muted hover:text-ink transition p-1.5 rounded-lg hover:bg-canvas shrink-0 cursor-pointer ml-3"
                    title="Close Modal"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Modal Scrollable Content -->
            <div class="p-5 sm:p-6 space-y-6 overflow-y-auto grow">
                @if($selectedOrg)
                    <!-- SECTION 1: Create New User for this Organization -->
                    <div class="bg-canvas/50 border border-border rounded-xl p-4 sm:p-5 space-y-4">
                        <div class="flex items-center justify-between">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-ink flex items-center gap-2">
                                <span class="p-1 rounded-md bg-primary/10 text-primary">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                                    </svg>
                                </span>
                                <span>Create &amp; Assign New User</span>
                            </h4>
                            <span class="text-[11px] text-muted">Assign directly to <strong class="text-ink font-semibold">{{ $selectedOrg->name }}</strong></span>
                        </div>

                        <div class="space-y-3.5 text-xs">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                                <div>
                                    <label class="font-bold text-ink">Full Name <span class="text-red-500">*</span></label>
                                    <input 
                                        type="text" 
                                        wire:model="newUserName" 
                                        placeholder="e.g. Rahul Sharma"
                                        class="w-full h-8 px-3.5 rounded-lg border border-border bg-surface text-ink text-xs focus:ring-2 focus:ring-primary/20 focus:border-primary transition mt-1"
                                    >
                                    @error('newUserName') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="font-bold text-ink">Email Address <span class="text-red-500">*</span></label>
                                    <input 
                                        type="email" 
                                        wire:model="newUserEmail" 
                                        placeholder="e.g. rahul@company.com"
                                        class="w-full h-8 px-3.5 rounded-lg border border-border bg-surface text-ink text-xs focus:ring-2 focus:ring-primary/20 focus:border-primary transition mt-1"
                                    >
                                    @error('newUserEmail') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                                <div>
                                    <label class="font-bold text-ink">Assign Role <span class="text-red-500">*</span></label>
                                    <x-ui.themed-select 
                                        wire:model="newUserRole" 
                                        :options="$this->availableRoles"
                                        placeholder="Select Role" 
                                        class="w-full mt-1" 
                                    />
                                    @error('newUserRole') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="font-bold text-ink">Custom Password <span class="text-muted font-normal">(Optional)</span></label>
                                    <input 
                                        type="text" 
                                        wire:model="newUserPassword" 
                                        placeholder="Auto-generated if left blank"
                                        class="w-full h-8 px-3.5 rounded-lg border border-border bg-surface text-ink text-xs focus:ring-2 focus:ring-primary/20 focus:border-primary transition mt-1"
                                    >
                                </div>
                            </div>

                            <div class="pt-1 flex justify-end">
                                <button 
                                    type="button"
                                    wire:click="createUserForOrganization"
                                    class="px-4 py-2 bg-ink text-white text-xs font-bold rounded-lg hover:bg-neutral-800 transition shadow-xs flex items-center gap-1.5 cursor-pointer"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                    </svg>
                                    <span>Create &amp; Assign User</span>
                                </button>
                            </div>

                            @if($generatedInviteLink)
                                <div class="p-3.5 bg-surface border border-border rounded-xl space-y-2 mt-2 shadow-xs">
                                    <div class="flex items-center justify-between">
                                        <span class="text-[11px] font-bold text-ink flex items-center gap-1.5">
                                            <svg class="w-3.5 h-3.5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                                            </svg>
                                            <span>Shareable Activation Link</span>
                                        </span>
                                        <span class="text-[10px] text-muted">Single-use reset token</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <input 
                                            type="text" 
                                            readonly 
                                            value="{{ $generatedInviteLink }}" 
                                            class="w-full h-8 px-3 text-[11px] font-mono bg-canvas border border-border rounded-lg text-ink select-all"
                                        >
                                        <button 
                                            type="button" 
                                            x-data="{ copied: false }" 
                                            @click="navigator.clipboard.writeText('{{ $generatedInviteLink }}'); copied = true; $wire.dispatch('toast', { type: 'success', title: 'Copied', message: 'Activation link copied to clipboard.' }); setTimeout(function() { copied = false; }, 2000)" 
                                            class="px-3 h-8 bg-ink text-white rounded-lg hover:bg-neutral-800 text-xs font-bold transition shrink-0 flex items-center gap-1.5 shadow-xs cursor-pointer"
                                        >
                                            <svg x-show="!copied" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                            </svg>
                                            <svg x-show="copied" class="w-3.5 h-3.5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                            </svg>
                                            <span x-text="copied ? 'Copied' : 'Copy'"></span>
                                        </button>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- SECTION 2: Current Assigned Members -->
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-ink flex items-center gap-2">
                                <span class="p-1 rounded-md bg-canvas text-muted border border-border">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                    </svg>
                                </span>
                                <span>Assigned Members</span>
                                <span class="px-2 py-0.2 rounded-full text-[10px] font-bold bg-canvas text-ink border border-border font-mono">
                                    {{ $selectedOrg->users->count() }}
                                </span>
                            </h4>
                        </div>

                        <div class="space-y-2">
                            @forelse($selectedOrg->users as $member)
                                <div class="p-3 bg-surface border border-border rounded-xl flex items-center justify-between gap-3 shadow-2xs hover:border-ink/20 transition">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 border border-border text-ink font-bold text-xs flex items-center justify-center shrink-0">
                                            {{ strtoupper(substr($member->name, 0, 1)) }}
                                        </div>
                                        <div class="min-w-0">
                                            <div class="font-bold text-xs text-ink truncate">{{ $member->name }}</div>
                                            <div class="text-[11px] text-muted font-mono truncate">{{ $member->email }}</div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2 shrink-0">
                                        <span class="px-2.5 py-0.5 text-[10px] font-bold rounded-pill bg-canvas text-ink border border-border uppercase">
                                            {{ $member->roles->first()?->name ? ucwords(str_replace(['-', '_'], ' ', $member->roles->first()->name)) : 'No Role' }}
                                        </span>

                                        <a 
                                            href="{{ route('admin.users.impersonate', $member->id) }}" 
                                            class="p-1.5 rounded-lg border border-border bg-surface hover:bg-purple-50 text-purple-700 hover:border-purple-300 transition shadow-2xs inline-flex items-center justify-center" 
                                            title="Impersonate User"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                                            </svg>
                                        </a>

                                        <button 
                                            type="button"
                                            wire:click="removeUserFromOrganization({{ $member->id }})"
                                            class="p-1.5 rounded-lg border border-border bg-surface hover:bg-red-50 text-red-600 hover:border-red-300 transition shadow-2xs cursor-pointer inline-flex items-center justify-center"
                                            title="Unassign from Organization"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            @empty
                                <div class="p-6 text-center text-xs text-muted bg-canvas/30 rounded-xl border border-dashed border-border">
                                    No users currently assigned to this organization. Create the first one above!
                                </div>
                            @endforelse
                        </div>
                    </div>
                @endif
            </div>

            <!-- Modal Footer -->
            <div class="px-5 sm:px-6 py-3.5 bg-canvas/40 border-t border-border flex items-center justify-end shrink-0">
                <button 
                    type="button" 
                    wire:click="closeUserModal" 
                    class="px-4 py-2 border border-border bg-surface text-ink text-xs font-semibold rounded-lg hover:bg-canvas transition cursor-pointer shadow-2xs"
                >
                    Close
                </button>
            </div>
        </div>
    </x-modal>
</div>