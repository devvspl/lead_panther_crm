@if($showExportModal)
    @php
        $exportCategories = static::exportCategories();
        $totalAvailableColumns = 0;
        foreach ($exportCategories as $cat) {
            $totalAvailableColumns += count($cat['fields']);
        }
    @endphp
    <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4"
         wire:keydown.escape="closeExportModal">
        <div class="bg-surface rounded-2xl border border-border shadow-2xl max-w-3xl w-full p-5 sm:p-6 space-y-4 max-h-[calc(100dvh-2rem)] flex flex-col animate-in fade-in zoom-in-95 duration-150"
             x-data="{
                 expanded: {
                     lead_information: true,
                     project_campaign: true,
                     assignment_details: true,
                     lead_status_followup: true,
                     additional_details: true
                 }
             }">

            <!-- Modal Header -->
            <div class="flex items-start justify-between pb-3.5 border-b border-border shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-200/80 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-bold text-ink">Export Leads to Excel</h3>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                Super Admin Export
                            </span>
                        </div>
                        <p class="text-xs text-muted mt-0.5">
                            Customize and select the exact columns to include in your Excel export.
                        </p>
                    </div>
                </div>
                <button type="button" wire:click="closeExportModal" class="text-muted hover:text-ink p-1 rounded-lg hover:bg-canvas transition cursor-pointer">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Scope Bar (If rows selected) -->
            @if(!empty($selectedRows))
                <div class="p-2.5 rounded-xl bg-canvas border border-border flex items-center justify-between text-xs shrink-0">
                    <span class="text-muted font-medium">Export Scope:</span>
                    <div class="inline-flex rounded-lg border border-border bg-surface p-0.5">
                        <button type="button"
                                wire:click="$set('exportScope', 'all')"
                                class="px-2.5 py-1 text-xs font-semibold rounded-md transition cursor-pointer {{ $exportScope === 'all' ? 'bg-primary text-white shadow-2xs' : 'text-muted hover:text-ink' }}">
                            All Matching Leads ({{ $this->exportMatchingCount }})
                        </button>
                        <button type="button"
                                wire:click="$set('exportScope', 'selected')"
                                class="px-2.5 py-1 text-xs font-semibold rounded-md transition cursor-pointer {{ $exportScope === 'selected' ? 'bg-primary text-white shadow-2xs' : 'text-muted hover:text-ink' }}">
                            Selected Leads Only ({{ count($selectedRows) }})
                        </button>
                    </div>
                </div>
            @endif

            <!-- Quick Actions & Column Count Bar -->
            <div class="flex flex-wrap items-center justify-between gap-2 text-xs py-1 shrink-0">
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-primary/10 text-primary border border-primary/20">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>{{ count($selectedExportColumns) }} of {{ $totalAvailableColumns }} columns selected</span>
                    </span>

                    <span class="text-muted text-[11px] hidden sm:inline">
                        • {{ $this->exportMatchingCount }} total leads will be exported
                    </span>
                </div>

                <div class="flex items-center gap-2">
                    <button type="button" wire:click="selectAllExportColumns"
                        class="text-xs font-semibold text-primary hover:text-primary-dark transition cursor-pointer">
                        Select All
                    </button>
                    <span class="text-muted">&bull;</span>
                    <button type="button" wire:click="clearAllExportColumns"
                        class="text-xs font-semibold text-rose-600 hover:text-rose-700 transition cursor-pointer">
                        Clear All
                    </button>
                    <span class="text-muted">&bull;</span>
                    <button type="button" wire:click="resetExportColumnsToDefault"
                        class="text-xs font-semibold text-muted hover:text-ink transition cursor-pointer">
                        Reset Default
                    </button>
                </div>
            </div>

            <!-- Validation Error Alert -->
            @if(!empty($exportValidationError))
                <div class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs flex items-center gap-2.5 shrink-0 animate-in fade-in duration-100">
                    <svg class="w-4 h-4 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="font-semibold">{{ $exportValidationError }}</span>
                </div>
            @endif

            <!-- Categorized Fields Section (Scrollable) -->
            <div class="flex-1 overflow-y-auto overscroll-contain pr-1 space-y-3 max-h-[50vh]">
                @foreach($exportCategories as $categoryKey => $cat)
                    @php
                        $catFieldKeys = array_keys($cat['fields']);
                        $selectedInCat = array_intersect($catFieldKeys, $selectedExportColumns);
                        $selectedInCatCount = count($selectedInCat);
                        $isAllSelected = $selectedInCatCount === count($catFieldKeys);
                        $isPartiallySelected = $selectedInCatCount > 0 && !$isAllSelected;
                    @endphp
                    <div class="rounded-xl border border-border bg-canvas/30 overflow-hidden">
                        <!-- Category Header Bar with Checkbox and Accordion Toggle -->
                        <div class="flex items-center justify-between p-3 bg-canvas/70 hover:bg-canvas border-b border-border/60 transition select-none">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <input type="checkbox"
                                       wire:click="toggleExportCategory('{{ $categoryKey }}')"
                                       @checked($isAllSelected)
                                       class="rounded border-neutral-300 text-primary focus:ring-primary h-4 w-4 shrink-0 cursor-pointer">
                                <div class="min-w-0" @click="expanded.{{ $categoryKey }} = !expanded.{{ $categoryKey }}">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-bold text-ink uppercase tracking-wider cursor-pointer">
                                            {{ $cat['title'] }}
                                        </span>
                                        <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full {{ $selectedInCatCount > 0 ? 'bg-primary/10 text-primary border border-primary/20' : 'bg-surface text-muted border border-border' }}">
                                            {{ $selectedInCatCount }} / {{ count($catFieldKeys) }}
                                        </span>
                                    </div>
                                    <p class="text-[11px] text-muted truncate cursor-pointer">
                                        {{ $cat['description'] }}
                                    </p>
                                </div>
                            </div>

                            <button type="button"
                                    @click="expanded.{{ $categoryKey }} = !expanded.{{ $categoryKey }}"
                                    class="p-1.5 rounded-lg text-muted hover:text-ink hover:bg-surface transition cursor-pointer"
                                    title="Expand / Collapse category">
                                <svg class="w-4 h-4 transform transition-transform duration-150"
                                     :class="expanded.{{ $categoryKey }} ? 'rotate-180' : ''"
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>
                        </div>

                        <!-- Fields Grid (Collapsible) -->
                        <div x-show="expanded.{{ $categoryKey }}"
                             x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="opacity-0 -translate-y-1"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             class="p-3 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2">
                            @foreach($cat['fields'] as $fieldKey => $f)
                                @php
                                    $isChecked = in_array($fieldKey, $selectedExportColumns, true);
                                @endphp
                                <label class="flex items-center gap-2.5 p-2.5 rounded-xl border transition-all cursor-pointer {{ $isChecked ? 'bg-primary/5 border-primary/40 shadow-2xs' : 'bg-surface border-border hover:border-neutral-300' }}">
                                    <input type="checkbox"
                                           wire:model.live="selectedExportColumns"
                                           value="{{ $fieldKey }}"
                                           class="rounded border-neutral-300 text-primary focus:ring-primary h-4 w-4 shrink-0 cursor-pointer">
                                    <div class="min-w-0 flex-1">
                                        <span class="text-xs font-semibold {{ $isChecked ? 'text-ink' : 'text-muted' }}">
                                            {{ $f['label'] }}
                                        </span>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Modal Footer -->
            <div class="flex items-center justify-between pt-3.5 border-t border-border shrink-0">
                <div class="text-[11px] text-muted hidden sm:block">
                    Applies active filters, searches, and authorized lead scope.
                </div>
                <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
                    <button type="button" wire:click="closeExportModal"
                        class="px-4 py-2 rounded-lg border border-border bg-canvas text-ink text-xs font-semibold hover:bg-neutral-200/60 transition cursor-pointer">
                        Cancel
                    </button>
                    <button type="button" wire:click="confirmExport"
                        wire:loading.attr="disabled"
                        wire:target="confirmExport"
                        class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold transition shadow-2xs cursor-pointer inline-flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                        <!-- Idle Icon -->
                        <svg wire:loading.remove wire:target="confirmExport" class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <!-- Loading Spinner -->
                        <svg wire:loading wire:target="confirmExport" class="animate-spin w-4 h-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span wire:loading.remove wire:target="confirmExport">Export Leads to Excel</span>
                        <span wire:loading wire:target="confirmExport">Generating Excel...</span>
                    </button>
                </div>
            </div>

        </div>
    </div>
@endif
