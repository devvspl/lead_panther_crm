<div class="space-y-6" @if($isSyncRunning) wire:poll.2s="checkSyncProgress" @endif>
    <!-- Header with Breadcrumbs & Diagnostic Status -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2 text-xs text-muted mb-1">
                <span>System Administration</span>
                <span>/</span>
                <span class="text-ink font-semibold">Deployment &amp; Git Sync</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-ink flex items-center gap-2.5">
                <svg class="w-6 h-6 text-primary flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                </svg>
                <span>Codebase Git Sync &amp; Deployment</span>
            </h1>
            <p class="text-xs text-muted">Pull production releases, review commits, safely revert codebase changes, and deploy with encrypted access.</p>
        </div>

        <div class="flex items-center gap-3">
            <button 
                type="button" 
                wire:click="refreshStatus" 
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-border bg-surface text-ink text-xs font-semibold hover:bg-canvas transition shadow-2xs cursor-pointer"
            >
                <svg class="w-3.5 h-3.5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <span>Refresh Status</span>
            </button>
        </div>
    </div>

    <!-- Active Repo Summary Banner -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- 1. Active Branch -->
        <div class="bg-surface rounded-card border border-border p-4 shadow-sm flex items-start justify-between">
            <div>
                <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Active Branch</span>
                <div class="text-lg font-bold text-ink mt-1 flex items-center gap-1.5 font-mono">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" />
                    </svg>
                    <span>{{ $repoStatus['current_branch'] ?? 'main' }}</span>
                </div>
                <div class="text-[11px] text-muted mt-1 font-mono">Commit: {{ $repoStatus['short_commit'] ?? 'unknown' }}</div>
            </div>
            <span class="px-2 py-0.5 text-[10px] font-bold rounded-pill bg-emerald-50 text-emerald-700 border border-emerald-200">
                Connected
            </span>
        </div>

        <!-- 2. Remote Tracking -->
        <div class="bg-surface rounded-card border border-border p-4 shadow-sm flex items-start justify-between">
            <div>
                <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Sync Differential</span>
                <div class="text-lg font-bold text-ink mt-1 flex items-center gap-2 font-mono">
                    <span class="{{ ($repoStatus['behind_count'] ?? 0) > 0 ? 'text-amber-600' : 'text-emerald-600' }}">
                        ↓ {{ $repoStatus['behind_count'] ?? 0 }} Behind
                    </span>
                    <span class="text-muted text-xs">/</span>
                    <span class="{{ ($repoStatus['ahead_count'] ?? 0) > 0 ? 'text-blue-600' : 'text-muted' }}">
                        ↑ {{ $repoStatus['ahead_count'] ?? 0 }} Ahead
                    </span>
                </div>
                <div class="text-[11px] text-muted mt-1">Relative to origin/{{ $selectedBranch }}</div>
            </div>
            <div class="h-8 w-8 rounded-full bg-canvas border border-border flex items-center justify-center text-ink">
                <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            </div>
        </div>

        <!-- 3. Local Working Tree -->
        <div class="bg-surface rounded-card border border-border p-4 shadow-sm flex items-start justify-between">
            <div>
                <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Working Tree</span>
                <div class="text-lg font-bold text-ink mt-1">
                    {{ $repoStatus['modified_count'] ?? 0 }} Modified {{ ($repoStatus['modified_count'] ?? 0) === 1 ? 'File' : 'Files' }}
                </div>
                <div class="text-[11px] {{ ($repoStatus['modified_count'] ?? 0) > 0 ? 'text-amber-600 font-semibold' : 'text-emerald-600' }} mt-1">
                    {{ ($repoStatus['modified_count'] ?? 0) > 0 ? 'Uncommitted changes present' : 'Working directory clean' }}
                </div>
            </div>
            <div class="h-8 w-8 rounded-full bg-canvas border border-border flex items-center justify-center text-ink">
                <svg class="w-4 h-4 {{ ($repoStatus['modified_count'] ?? 0) > 0 ? 'text-amber-500' : 'text-emerald-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            </div>
        </div>

        <!-- 4. Security & Encryption -->
        <div class="bg-surface rounded-card border border-border p-4 shadow-sm flex items-start justify-between">
            <div>
                <span class="text-[11px] font-bold uppercase tracking-wider text-muted">Credentials Security</span>
                <div class="text-base font-bold text-ink mt-1 flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    <span>{{ $hasStoredToken ? 'Encrypted at Rest' : 'Token Missing' }}</span>
                </div>
                <div class="text-[11px] text-muted mt-1">AES-256 via integration table</div>
            </div>
            <span class="px-2 py-0.5 text-[10px] font-bold rounded-pill {{ $hasStoredToken ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200' }}">
                {{ $hasStoredToken ? 'Protected' : 'Setup Required' }}
            </span>
        </div>
    </div>

    <!-- Active Job Notification Bar -->
    @if($isSyncRunning)
        <div class="p-4 rounded-card border border-blue-200 bg-blue-50 text-blue-900 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-xs">
            <div class="flex items-center space-x-3 text-xs">
                <svg class="animate-spin h-5 w-5 text-blue-600 flex-shrink-0" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <div>
                    <div class="font-bold text-sm">Git Operation in Progress...</div>
                    <p class="text-blue-700 text-xs">Executing process on branch <span class="font-mono font-bold">{{ $selectedBranch }}</span>. Output will refresh automatically.</p>
                </div>
            </div>
            <button 
                type="button" 
                wire:click="cancelSync" 
                class="px-3 py-1.5 rounded-lg border border-blue-300 bg-white text-blue-800 text-xs font-semibold hover:bg-blue-100 transition shrink-0 cursor-pointer shadow-2xs"
            >
                Dismiss / Reset Status
            </button>
        </div>
    @endif

    <!-- Modern Segmented Navigation Tabs -->
    <div class="p-1 bg-canvas rounded-xl border border-border flex items-center gap-1.5 overflow-x-auto shadow-2xs">
        <button 
            type="button" 
            wire:click="$set('activeTab', 'pull')"
            class="px-3.5 py-2 rounded-lg text-xs font-semibold transition flex items-center gap-2 flex-shrink-0 cursor-pointer {{ $activeTab === 'pull' ? 'bg-surface text-ink font-bold shadow-xs border border-border' : 'text-muted hover:text-ink hover:bg-surface/50' }}"
        >
            <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            <span>1. Pull Latest</span>
            @if(($repoStatus['behind_count'] ?? 0) > 0)
                <span class="px-1.5 py-0.2 text-[9px] rounded-pill bg-amber-500 text-white font-bold">{{ $repoStatus['behind_count'] }}</span>
            @endif
        </button>

        <button 
            type="button" 
            wire:click="$set('activeTab', 'push')"
            class="px-3.5 py-2 rounded-lg text-xs font-semibold transition flex items-center gap-2 flex-shrink-0 cursor-pointer {{ $activeTab === 'push' ? 'bg-surface text-ink font-bold shadow-xs border border-border' : 'text-muted hover:text-ink hover:bg-surface/50' }}"
        >
            <svg class="w-4 h-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
            <span>2. Push Changes</span>
            @if(($repoStatus['modified_count'] ?? 0) > 0)
                <span class="px-1.5 py-0.2 text-[9px] rounded-pill bg-rose-500 text-white font-bold">{{ $repoStatus['modified_count'] }}</span>
            @endif
        </button>

        <button 
            type="button" 
            wire:click="$set('activeTab', 'history')"
            class="px-3.5 py-2 rounded-lg text-xs font-semibold transition flex items-center gap-2 flex-shrink-0 cursor-pointer {{ $activeTab === 'history' ? 'bg-surface text-ink font-bold shadow-xs border border-border' : 'text-muted hover:text-ink hover:bg-surface/50' }}"
        >
            <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>3. History &amp; Revert</span>
            @if(count($commitHistory) > 0)
                <span class="px-1.5 py-0.2 text-[9px] rounded-pill bg-canvas text-muted border border-border font-mono">{{ count($commitHistory) }}</span>
            @endif
        </button>

        <button 
            type="button" 
            wire:click="$set('activeTab', 'settings')"
            class="px-3.5 py-2 rounded-lg text-xs font-semibold transition flex items-center gap-2 flex-shrink-0 cursor-pointer {{ $activeTab === 'settings' ? 'bg-surface text-ink font-bold shadow-xs border border-border' : 'text-muted hover:text-ink hover:bg-surface/50' }}"
        >
            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <span>Repository Connection</span>
        </button>

        <button 
            type="button" 
            wire:click="$set('activeTab', 'audit')"
            class="px-3.5 py-2 rounded-lg text-xs font-semibold transition flex items-center gap-2 flex-shrink-0 cursor-pointer {{ $activeTab === 'audit' ? 'bg-surface text-ink font-bold shadow-xs border border-border' : 'text-muted hover:text-ink hover:bg-surface/50' }}"
        >
            <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            <span>Audit Trail</span>
        </button>
    </div>

    <!-- TAB 1: PULL LATEST CHANGES -->
    @if($activeTab === 'pull')
        <div class="space-y-6">
            <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-border">
                    <div>
                        <h2 class="text-base font-bold text-ink">Pull Updates from Remote Repository</h2>
                        <p class="text-xs text-muted mt-0.5">Fetches and merges the latest commits from <span class="font-mono font-bold text-ink">origin/{{ $selectedBranch }}</span> into this environment.</p>
                    </div>

                    <!-- Target Branch Dropdown -->
                    <div class="flex items-center space-x-2">
                        <span class="text-xs font-semibold text-muted">Target Branch:</span>
                        <select 
                            wire:model.live="selectedBranch" 
                            class="h-8 px-3 rounded-lg border border-border bg-canvas text-ink text-xs font-mono font-semibold"
                        >
                            @foreach($remoteBranches as $br)
                                <option value="{{ $br }}">{{ $br }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Status & Action Box -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-2 space-y-4">
                        <div class="p-4 bg-canvas rounded-xl border border-border space-y-3 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-ink uppercase text-[10px] tracking-wider">Latest Local Commit</span>
                                <span class="font-mono bg-surface px-2 py-0.5 rounded border border-border text-[11px] font-bold text-ink">{{ $repoStatus['short_commit'] ?? 'unknown' }}</span>
                            </div>
                            <p class="text-muted leading-relaxed font-mono text-[11px] bg-surface p-2.5 rounded-lg border border-border">
                                {{ $repoStatus['last_commit_info'] ?: 'No commit information available' }}
                            </p>
                        </div>

                        <!-- Recent Commits Table Component -->
                        @if(!empty($repoStatus['recent_commits']))
                            <div class="space-y-2">
                                <div class="text-xs font-bold text-ink">Recent Local Commit History</div>
                                
                                @php
                                    $localCommitCols = [
                                        ['key' => 'hash', 'label' => 'Hash', 'class' => 'font-mono font-bold text-primary', 'sortable' => false, 'priority' => 1],
                                        ['key' => 'message', 'label' => 'Message', 'render' => fn($row) => '<span class="font-medium text-ink truncate max-w-xs block" title="' . e(is_array($row) ? $row['message'] : $row->message) . '">' . e(is_array($row) ? $row['message'] : $row->message) . '</span>', 'sortable' => false, 'priority' => 1],
                                        ['key' => 'author', 'label' => 'Author', 'class' => 'text-muted', 'sortable' => false, 'priority' => 2],
                                        ['key' => 'date', 'label' => 'Date', 'class' => 'text-muted font-mono text-[11px]', 'sortable' => false, 'priority' => 2],
                                    ];
                                @endphp

                                <x-ui.advanced-table 
                                    :columns="$localCommitCols"
                                    :rows="$repoStatus['recent_commits']"
                                    :showSearch="false"
                                    :showFilterDropdown="false"
                                    :showConfigurations="false"
                                    emptyTitle="No Recent Commits"
                                    emptyMessage="No recent local commits found."
                                />
                            </div>
                        @endif
                    </div>

                    <!-- Trigger Button & Info -->
                    <div class="space-y-4 flex flex-col justify-between p-4 bg-canvas rounded-xl border border-border">
                        <div class="space-y-3">
                            <div class="font-bold text-xs text-ink uppercase tracking-wider">Pull Action Guardrails</div>
                            <ul class="text-xs text-muted space-y-2 list-disc pl-4">
                                <li>Pulls are executed via Symfony Process with strict branch validation.</li>
                                <li>Merge conflicts will halt without destructive automatic overwrites.</li>
                                <li>Post-pull actions (migrate, build) are explicit opt-in buttons below.</li>
                            </ul>
                        </div>

                        <button 
                            type="button" 
                            wire:click="startPull" 
                            wire:loading.attr="disabled"
                            class="w-full py-2.5 px-4 rounded-lg bg-ink text-white font-bold text-xs shadow-xs hover:bg-neutral-800 transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
                        >
                            <svg wire:loading.remove wire:target="startPull" class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            <svg wire:loading wire:target="startPull" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <span wire:loading.remove wire:target="startPull">Pull Now (origin/{{ $selectedBranch }})</span>
                            <span wire:loading wire:target="startPull">Executing Pull...</span>
                        </button>
                    </div>
                </div>

                <!-- Last Pull Output Console -->
                @if($lastJobResult && ($lastJobResult['action'] ?? '') === 'pull')
                    <div class="space-y-2 pt-4 border-t border-border">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-bold text-ink">Last Pull Output &amp; Result</span>
                            <span class="px-2 py-0.5 rounded-pill text-[10px] font-bold {{ ($lastJobResult['successful'] ?? false) ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200' }}">
                                {{ ($lastJobResult['successful'] ?? false) ? '✓ Succeeded' : '✕ Failed' }}
                            </span>
                        </div>
                        <div class="bg-black text-emerald-400 p-4 rounded-xl font-mono text-[11px] overflow-x-auto max-h-56 select-text whitespace-pre-wrap">
{{ $lastJobResult['stdout'] ?: ($lastJobResult['stderr'] ?: 'No output recorded') }}
                        </div>
                    </div>
                @endif
            </div>

            <!-- Opt-In Post-Pull Maintenance Actions -->
            <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-4">
                <div>
                    <h3 class="text-sm font-bold text-ink">Post-Pull Opt-In Follow-Up Actions</h3>
                    <p class="text-xs text-muted">Execute database migrations, package installations, or asset compilations explicitly after reviewing pulled changes.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <button 
                        type="button" 
                        wire:click="runFollowup('migrate')" 
                        wire:loading.attr="disabled"
                        class="p-3.5 bg-canvas rounded-xl border border-border text-left hover:border-ink transition space-y-1.5 cursor-pointer disabled:opacity-50"
                    >
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-xs text-ink">Run Migrations</span>
                            <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/></svg>
                        </div>
                        <p class="text-[11px] text-muted font-mono">php artisan migrate --force</p>
                    </button>

                    <button 
                        type="button" 
                        wire:click="runFollowup('composer')" 
                        wire:loading.attr="disabled"
                        class="p-3.5 bg-canvas rounded-xl border border-border text-left hover:border-ink transition space-y-1.5 cursor-pointer disabled:opacity-50"
                    >
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-xs text-ink">Composer Install</span>
                            <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                        </div>
                        <p class="text-[11px] text-muted font-mono">composer install --no-dev</p>
                    </button>

                    <button 
                        type="button" 
                        wire:click="runFollowup('npm')" 
                        wire:loading.attr="disabled"
                        class="p-3.5 bg-canvas rounded-xl border border-border text-left hover:border-ink transition space-y-1.5 cursor-pointer disabled:opacity-50"
                    >
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-xs text-ink">Rebuild Assets</span>
                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        </div>
                        <p class="text-[11px] text-muted font-mono">npm run build</p>
                    </button>

                    <button 
                        type="button" 
                        wire:click="runFollowup('cache_all')" 
                        wire:loading.attr="disabled"
                        class="p-3.5 bg-canvas rounded-xl border border-border text-left hover:border-ink transition space-y-1.5 cursor-pointer disabled:opacity-50"
                    >
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-xs text-ink">Optimize Cache</span>
                            <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                        </div>
                        <p class="text-[11px] text-muted font-mono">php artisan optimize</p>
                    </button>
                </div>

                @if($lastFollowupResult)
                    <div class="p-3 bg-black text-emerald-400 rounded-xl font-mono text-[11px] overflow-x-auto select-text whitespace-pre-wrap mt-3">
                        <div class="text-white font-bold mb-1">[Action: {{ $lastFollowupResult['action'] }}] Status: {{ $lastFollowupResult['successful'] ? 'SUCCESS' : 'FAILED' }}</div>
{{ $lastFollowupResult['stdout'] ?: ($lastFollowupResult['stderr'] ?: 'Executed with no output') }}
                    </div>
                @endif
            </div>
        </div>
    @endif

    <!-- TAB 2: PUSH CODE CHANGES -->
    @if($activeTab === 'push')
        <div class="space-y-6">
            <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-6">
                <!-- Header -->
                <div class="flex items-start space-x-3 pb-4 border-b border-border">
                    <div class="p-2 rounded-lg bg-rose-50 text-rose-600 border border-rose-200">
                        <svg class="w-6 h-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-ink">Push Local Commits to Remote Repository</h2>
                        <p class="text-xs text-muted mt-0.5">Stages modified files, creates a commit, and pushes to <span class="font-mono font-bold text-rose-600">origin/{{ $selectedBranch }}</span>.</p>
                    </div>
                </div>

                <!-- Pre-Push Secret Scanner Warning -->
                @if($secretScanResult && $secretScanResult['has_secrets'])
                    <div class="p-4 rounded-xl border border-red-300 bg-red-50 text-red-900 space-y-2">
                        <div class="font-bold text-xs flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                            <span>Pre-Push Secret Scanner Alert — Push Blocked!</span>
                        </div>
                        <p class="text-xs">The automated pre-push scanner identified potential sensitive credentials or secret files in your modified changes:</p>
                        <ul class="list-disc pl-5 text-[11px] font-mono space-y-1">
                            @foreach($secretScanResult['findings'] as $finding)
                                <li>{{ $finding }}</li>
                            @endforeach
                        </ul>
                        <div class="pt-2">
                            <label class="flex items-center space-x-2 text-xs font-semibold text-red-900 cursor-pointer select-none">
                                <input type="checkbox" wire:model.live="overrideSecretBlock" class="rounded text-danger focus:ring-danger">
                                <span>I have verified these are false positives and explicitly wish to override the secret block.</span>
                            </label>
                        </div>
                    </div>
                @endif

                <!-- Modified Files Status -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-bold text-ink">Modified / Staged Files ({{ count($repoStatus['modified_files'] ?? []) }})</span>
                        <button type="button" wire:click="scanForSecrets" class="text-[11px] text-primary font-bold hover:underline cursor-pointer">
                            Run Secret Scanner Now
                        </button>
                    </div>

                    @if(empty($repoStatus['modified_files']))
                        <div class="p-4 rounded-xl bg-canvas border border-border text-center text-xs text-muted">
                            Working directory is clean. No unstaged or modified files to commit.
                        </div>
                    @else
                        <div class="border border-border rounded-xl overflow-hidden bg-surface max-h-48 overflow-y-auto font-mono text-xs divide-y divide-border">
                            @foreach($repoStatus['modified_files'] as $f)
                                <div class="py-2 px-3.5 flex items-center justify-between hover:bg-canvas/50">
                                    <span class="text-ink">{{ $f['path'] }}</span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $f['flag'] === 'M' ? 'bg-amber-100 text-amber-800' : ($f['flag'] === '??' ? 'bg-emerald-100 text-emerald-800' : 'bg-canvas text-muted') }}">
                                        {{ $f['flag'] }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <!-- Commit Message & Confirmation Input -->
                <div class="space-y-4 pt-2">
                    <div>
                        <label class="font-bold text-xs text-ink">Commit Message <span class="text-red-500">*</span></label>
                        <input 
                            type="text" 
                            wire:model="commitMessage" 
                            placeholder="e.g. fix: update lead sync configuration for production" 
                            class="w-full h-9 px-3 rounded-lg border border-border bg-canvas text-ink text-xs mt-1 focus:ring-2 focus:ring-primary/20 focus:border-primary"
                        >
                        @error('commitMessage') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                    </div>

                    <!-- Clean Confirmation Container -->
                    <div class="p-4 bg-canvas rounded-xl border border-border space-y-2.5">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                            <label class="font-bold text-xs text-ink">
                                Type <code class="px-2 py-0.5 rounded bg-surface border border-border font-mono font-bold text-rose-600 select-all">PUSH TO PRODUCTION</code> to confirm:
                            </label>
                            <span class="text-[11px] text-muted">Required safety confirmation phrase</span>
                        </div>
                        <input 
                            type="text" 
                            wire:model.live="confirmPushPhrase" 
                            placeholder="PUSH TO PRODUCTION" 
                            class="w-full h-9 px-3.5 rounded-lg border border-border bg-surface text-ink font-mono text-xs font-bold focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500"
                        >
                        @error('confirmPushPhrase') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button 
                            type="button" 
                            wire:click="startPush" 
                            wire:loading.attr="disabled"
                            @if($confirmPushPhrase !== 'PUSH TO PRODUCTION' || empty(trim($commitMessage))) disabled @endif
                            class="py-2.5 px-6 rounded-lg bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs shadow-xs transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                        >
                            <svg wire:loading.remove wire:target="startPush" class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            <svg wire:loading wire:target="startPush" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <span wire:loading.remove wire:target="startPush">Confirm &amp; Push to origin/{{ $selectedBranch }}</span>
                            <span wire:loading wire:target="startPush">Executing Push...</span>
                        </button>
                    </div>
                </div>

                <!-- Last Push Output Console -->
                @if($lastJobResult && ($lastJobResult['action'] ?? '') === 'push')
                    <div class="space-y-2 pt-4 border-t border-border">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-bold text-ink">Last Push Output &amp; Result</span>
                            <span class="px-2 py-0.5 rounded-pill text-[10px] font-bold {{ ($lastJobResult['successful'] ?? false) ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200' }}">
                                {{ ($lastJobResult['successful'] ?? false) ? '✓ Succeeded' : '✕ Failed' }}
                            </span>
                        </div>
                        <div class="bg-black text-emerald-400 p-4 rounded-xl font-mono text-[11px] overflow-x-auto max-h-56 select-text whitespace-pre-wrap">
{{ $lastJobResult['stdout'] ?: ($lastJobResult['stderr'] ?: 'No output recorded') }}
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <!-- TAB 3: COMMIT HISTORY & REVERT -->
    @if($activeTab === 'history')
        <div class="space-y-6">
            <!-- Safety Backups Header Panel -->
            @if(!empty($backupBranches))
                <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="p-1.5 bg-blue-50 text-blue-700 rounded-lg border border-blue-200">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2-2V9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                            </span>
                            <div>
                                <h3 class="text-sm font-bold text-ink">Pre-Revert Safety Backup Snapshots</h3>
                                <p class="text-xs text-muted">Branches automatically created and pushed prior to past revert operations. Restore anytime to undo a mistake.</p>
                            </div>
                        </div>
                    </div>

                    <div class="border border-border rounded-xl overflow-hidden bg-canvas text-xs divide-y divide-border">
                        @foreach($backupBranches as $backup)
                            <div class="p-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-surface/50 transition">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono font-bold text-ink">{{ $backup['name'] }}</span>
                                        <span class="font-mono text-[10px] text-primary bg-primary/10 px-1.5 py-0.5 rounded border border-primary/20">{{ $backup['short_commit'] }}</span>
                                    </div>
                                    <p class="text-[11px] text-muted mt-0.5">{{ $backup['message'] }} &bull; <span class="italic">{{ $backup['date'] }}</span></p>
                                </div>

                                <button 
                                    type="button" 
                                    @click="$dispatch('confirm-action', {
                                        title: 'Restore Backup Snapshot?',
                                        message: 'Are you sure you want to restore the codebase to backup snapshot \'{{ $backup['name'] }}\'? Local files will be replaced with this snapshot.',
                                        confirmText: 'Yes, Restore Backup',
                                        cancelText: 'Cancel',
                                        variant: 'warning',
                                        onConfirm: function() { return $wire.restoreBackup('{{ $backup['name'] }}'); }
                                    })"
                                    class="px-3 py-1.5 rounded-lg border border-border bg-surface text-ink text-xs font-semibold hover:border-ink transition shadow-2xs flex items-center gap-1.5 flex-shrink-0 cursor-pointer"
                                >
                                    <svg class="w-3.5 h-3.5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                    <span>Restore this Backup</span>
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Main Commit History Table -->
            <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-border">
                    <div>
                        <h2 class="text-base font-bold text-ink">Recent Commit History (Last 50 Commits)</h2>
                        <p class="text-xs text-muted mt-0.5">View log history on <span class="font-mono font-bold text-ink">{{ $selectedBranch }}</span> and revert codebase safely.</p>
                    </div>
                </div>

                @php
                    $commitColumns = [
                        ['key' => 'short_hash', 'label' => 'Commit', 'render' => function($row) use ($repoStatus) {
                            $short = is_array($row) ? $row['short_hash'] : $row->short_hash;
                            $isHead = ($repoStatus['short_commit'] ?? '') === $short;
                            $dot = $isHead ? '<span class="w-2 h-2 rounded-full bg-emerald-500" title="Current HEAD"></span>' : '';
                            return '<div class="font-mono font-bold text-primary flex items-center gap-1.5">' . $dot . '<span>' . e($short) . '</span></div>';
                        }, 'sortable' => false, 'priority' => 1],
                        ['key' => 'message', 'label' => 'Message', 'render' => fn($row) => '<div class="font-medium text-ink max-w-md truncate" title="' . e(is_array($row) ? $row['message'] : $row->message) . '">' . e(is_array($row) ? $row['message'] : $row->message) . '</div>', 'sortable' => false, 'priority' => 1],
                        ['key' => 'author', 'label' => 'Author', 'class' => 'text-muted', 'sortable' => false, 'priority' => 2],
                        ['key' => 'date', 'label' => 'Date', 'render' => fn($row) => '<span class="text-muted font-mono text-[11px]">' . \Carbon\Carbon::parse(is_array($row) ? $row['date'] : $row->date)->diffForHumans() . '</span>', 'sortable' => false, 'priority' => 2],
                        ['key' => 'action', 'label' => 'Action', 'align' => 'right', 'render' => fn($row) => '<div class="flex items-center justify-end"><button type="button" wire:click="openRevertModal(\'' . (is_array($row) ? $row['hash'] : $row->hash) . '\')" class="px-2.5 py-1 rounded-lg border border-border bg-canvas text-ink text-xs font-semibold hover:border-danger hover:text-danger transition shadow-2xs inline-flex items-center gap-1 cursor-pointer"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12.066 11.2a1 1 0 000 1.6l5.334 4A1 1 0 0019 16V8a1 1 0 00-1.6-.8l-5.334 4zM4.066 11.2a1 1 0 000 1.6l5.334 4A1 1 0 0011 16V8a1 1 0 00-1.6-.8l-5.334 4z"/></svg><span>Revert to here</span></button></div>', 'sortable' => false, 'priority' => 1],
                    ];
                @endphp

                <x-ui.advanced-table 
                    :columns="$commitColumns"
                    :rows="$commitHistory"
                    :showSearch="false"
                    :showFilterDropdown="false"
                    :showConfigurations="false"
                    emptyTitle="No Commit History"
                    emptyMessage="No commit history found on branch {{ $selectedBranch }}."
                />

                <!-- Last Revert Output Console & Follow-ups -->
                @if($lastJobResult && in_array($lastJobResult['action'] ?? '', ['revert_safe', 'revert_hard', 'restore_backup']))
                    <div class="space-y-4 pt-4 border-t border-border">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-bold text-ink">Last Revert / Restore Output</span>
                            <span class="px-2 py-0.5 rounded-pill text-[10px] font-bold {{ ($lastJobResult['successful'] ?? false) ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200' }}">
                                {{ ($lastJobResult['successful'] ?? false) ? '✓ Succeeded' : '✕ Failed' }}
                            </span>
                        </div>
                        <div class="bg-black text-emerald-400 p-4 rounded-xl font-mono text-[11px] overflow-x-auto max-h-56 select-text whitespace-pre-wrap">
{{ $lastJobResult['stdout'] ?: ($lastJobResult['stderr'] ?: 'No output recorded') }}
                        </div>

                        <!-- Post-Revert Follow-up Actions -->
                        <div class="p-4 bg-canvas rounded-xl border border-border space-y-3">
                            <div class="font-bold text-xs text-ink">Post-Revert Follow-Up Actions:</div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2">
                                <button type="button" wire:click="runFollowup('migrate')" class="px-3 py-2 rounded-lg border border-border bg-surface text-ink text-xs font-semibold hover:border-ink transition text-left cursor-pointer">
                                    1. Run Migrations
                                </button>
                                <button type="button" wire:click="runFollowup('composer')" class="px-3 py-2 rounded-lg border border-border bg-surface text-ink text-xs font-semibold hover:border-ink transition text-left cursor-pointer">
                                    2. Composer Install
                                </button>
                                <button type="button" wire:click="runFollowup('npm')" class="px-3 py-2 rounded-lg border border-border bg-surface text-ink text-xs font-semibold hover:border-ink transition text-left cursor-pointer">
                                    3. Rebuild Assets (npm)
                                </button>
                                <button type="button" wire:click="runFollowup('cache_all')" class="px-3 py-2 rounded-lg border border-border bg-surface text-ink text-xs font-semibold hover:border-ink transition text-left cursor-pointer">
                                    4. Optimize Cache
                                </button>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <!-- TAB 4: REPOSITORY CONNECTION SETTINGS -->
    @if($activeTab === 'settings')
        <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-6">
            <div class="pb-4 border-b border-border">
                <h2 class="text-base font-bold text-ink">Git Repository Connection &amp; Authentication</h2>
                <p class="text-xs text-muted mt-0.5">Configure HTTPS remote repository credentials. Tokens are encrypted using Laravel's encryption engine and never logged.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                <div class="md:col-span-2">
                    <label class="font-bold text-ink">Git Remote URL <span class="text-red-500">*</span></label>
                    <input 
                        type="text" 
                        wire:model="remoteUrl" 
                        placeholder="https://github.com/devvspl/lead_panther_crm.git" 
                        class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs font-mono mt-1 focus:ring-2 focus:ring-primary/20 focus:border-primary"
                    >
                    @error('remoteUrl') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="font-bold text-ink">Git Username / Organization</label>
                    <input 
                        type="text" 
                        wire:model="username" 
                        placeholder="devvspl" 
                        class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs font-mono mt-1 focus:ring-2 focus:ring-primary/20 focus:border-primary"
                    >
                    @error('username') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="font-bold text-ink">Default Branch <span class="text-red-500">*</span></label>
                    <input 
                        type="text" 
                        wire:model="defaultBranch" 
                        placeholder="main" 
                        class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs font-mono mt-1 focus:ring-2 focus:ring-primary/20 focus:border-primary"
                    >
                    @error('defaultBranch') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                </div>

                <div class="md:col-span-2">
                    <div class="flex items-center justify-between">
                        <label class="font-bold text-ink">Personal Access Token (Encrypted) <span class="text-red-500">*</span></label>
                        @if($hasStoredToken && !$isReplacingToken)
                            <button 
                                type="button" 
                                wire:click="$set('isReplacingToken', true)" 
                                class="text-[11px] text-primary font-bold hover:underline cursor-pointer"
                            >
                                Replace Token
                            </button>
                        @endif
                    </div>

                    @if($hasStoredToken && !$isReplacingToken)
                        <div class="flex items-center space-x-2 mt-1">
                            <input 
                                type="password" 
                                disabled 
                                value="••••••••••••••••••••••••••••••••" 
                                class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas/60 text-muted text-xs font-mono"
                            >
                            <span class="px-2.5 py-1 text-[10px] font-bold rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200 flex-shrink-0">
                                Encrypted
                            </span>
                        </div>
                    @else
                        <input 
                            type="password" 
                            wire:model="accessToken" 
                            placeholder="ghp_... or github_pat_..." 
                            class="w-full h-8 px-3.5 rounded-lg border border-border bg-canvas text-ink text-xs font-mono mt-1 focus:ring-2 focus:ring-primary/20 focus:border-primary"
                        >
                        @error('accessToken') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                    @endif
                </div>
            </div>

            <!-- Test Connection Banner -->
            @if($connectionTestResult)
                <div class="p-4 rounded-xl border {{ $connectionTestResult['successful'] ? 'bg-emerald-50 border-emerald-200 text-emerald-900' : 'bg-red-50 border-red-200 text-red-900' }} text-xs space-y-1">
                    <div class="font-bold flex items-center gap-1.5">
                        <span>{{ $connectionTestResult['successful'] ? '✓ Connection Verified' : '✕ Connection Error' }}</span>
                    </div>
                    <p>{{ $connectionTestResult['message'] }}</p>
                </div>
            @endif

            <div class="flex items-center justify-between pt-4 border-t border-border">
                <button 
                    type="button" 
                    wire:click="testConnection" 
                    wire:loading.attr="disabled"
                    class="px-4 py-2 rounded-lg border border-border bg-canvas text-ink text-xs font-semibold hover:bg-surface transition shadow-2xs flex items-center gap-2 cursor-pointer"
                >
                    <svg wire:loading.remove wire:target="testConnection" class="w-3.5 h-3.5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    <span wire:loading.remove wire:target="testConnection">Test Connection (git ls-remote)</span>
                    <span wire:loading wire:target="testConnection">Testing...</span>
                </button>

                <button 
                    type="button" 
                    wire:click="saveSettings" 
                    class="px-5 py-2 rounded-lg bg-ink text-white text-xs font-bold shadow-xs hover:bg-neutral-800 transition cursor-pointer"
                >
                    Save Credentials
                </button>
            </div>
        </div>
    @endif

    <!-- TAB 5: AUDIT TRAIL -->
    @if($activeTab === 'audit')
        <div class="bg-surface rounded-card border border-border p-6 shadow-sm space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-border">
                <div>
                    <h2 class="text-base font-bold text-ink">Git Deployment Audit Logs</h2>
                    <p class="text-xs text-muted">Immutable trail of every pull, push, and maintenance command executed by administrators.</p>
                </div>
            </div>

            @php
                $auditColumns = [
                    ['key' => 'action', 'label' => 'Action', 'render' => function($row) {
                        $isHigh = str_contains($row->action, 'push') || str_contains($row->action, 'hard');
                        $isMed = str_contains($row->action, 'pull') || str_contains($row->action, 'safe');
                        $badgeClass = $isHigh ? 'bg-red-100 text-red-800' : ($isMed ? 'bg-blue-100 text-blue-800' : 'bg-canvas text-ink');
                        return '<span class="px-2 py-0.5 rounded text-[10px] font-bold font-mono ' . $badgeClass . '">' . e($row->action) . '</span>';
                    }, 'sortable' => false, 'priority' => 1],
                    ['key' => 'user', 'label' => 'User', 'render' => fn($row) => '<span class="font-medium text-ink">' . e($row->user?->name ?: 'System') . '</span>', 'sortable' => false, 'priority' => 1],
                    ['key' => 'to_value', 'label' => 'Details', 'render' => fn($row) => '<span class="font-mono text-[11px] text-muted truncate max-w-md block" title="' . e($row->to_value) . '">' . e($row->to_value) . '</span>', 'sortable' => false, 'priority' => 2],
                    ['key' => 'created_at', 'label' => 'Timestamp', 'render' => fn($row) => '<span class="text-muted text-[11px] font-mono">' . ($row->created_at ? $row->created_at->format('M d, Y H:i:s') : 'N/A') . '</span>', 'sortable' => false, 'priority' => 2],
                ];
            @endphp

            <x-ui.advanced-table 
                :columns="$auditColumns"
                :rows="$gitAuditLogs"
                :showSearch="false"
                :showFilterDropdown="false"
                :showConfigurations="false"
                emptyTitle="No Audit Logs"
                emptyMessage="No Git deployment audit logs recorded yet."
            />
        </div>
    @endif

    <!-- INTERACTIVE REVERT MODAL -->
    @if($showRevertModal && $selectedCommit)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
            <div class="bg-surface rounded-card border border-border max-w-2xl w-full p-6 shadow-2xl space-y-6 animate-in fade-in zoom-in-95">
                <!-- Modal Header -->
                <div class="flex items-start justify-between pb-4 border-b border-border">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-amber-50 dark:bg-amber-950/40 text-amber-600 rounded-lg border border-amber-200 dark:border-amber-900">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-ink">Revert Codebase to Prior Commit</h2>
                            <p class="text-xs text-muted">Target: <span class="font-mono font-bold text-ink">{{ $selectedCommit['short_hash'] }}</span> &bull; Author: {{ $selectedCommit['author'] }}</p>
                        </div>
                    </div>

                    <button type="button" wire:click="closeRevertModal" class="text-muted hover:text-ink cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <!-- Commit Details Card -->
                <div class="p-3 bg-canvas rounded-xl border border-border text-xs space-y-1">
                    <div class="font-bold text-ink font-mono text-[11px]">{{ $selectedCommit['message'] }}</div>
                    <div class="text-muted text-[10px] font-mono">Full Hash: {{ $selectedCommit['hash'] }}</div>
                </div>

                <!-- Pre-Revert Safety Backup Guarantee -->
                <div class="p-3.5 bg-blue-50 dark:bg-blue-950/30 rounded-xl border border-blue-200 dark:border-blue-900 text-xs text-blue-900 dark:text-blue-200 flex items-start gap-2.5">
                    <svg class="w-4 h-4 text-blue-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    <div>
                        <span class="font-bold">Automated Safety Snapshot Guaranteed:</span> A safety backup branch <span class="font-mono font-bold">backup/pre-revert-{{ date('Ymd') }}...</span> will be created at current HEAD and pushed to remote before any revert runs.
                    </div>
                </div>

                <!-- Explicit Revert Strategy Options -->
                <div class="space-y-3">
                    <div class="font-bold text-xs text-ink uppercase tracking-wider">Choose Revert Strategy (Required):</div>

                    <!-- OPTION A: SAFE REVERT -->
                    <label class="block p-4 rounded-xl border-2 transition cursor-pointer {{ $revertStrategy === 'safe' ? 'border-primary bg-primary/5' : 'border-border bg-canvas hover:border-ink/40' }}">
                        <div class="flex items-start gap-3">
                            <input type="radio" wire:model.live="revertStrategy" value="safe" class="mt-1 text-primary focus:ring-primary">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-xs text-ink">Option A: Safe Revert (Recommended)</span>
                                    <span class="px-2 py-0.2 text-[9px] font-bold rounded-pill bg-emerald-100 text-emerald-800">Non-Destructive</span>
                                </div>
                                <p class="text-xs text-muted mt-1 leading-relaxed">
                                    Creates new commit(s) that undo later changes. Full history stays intact. Safe even if other team members or production systems have already pulled this branch.
                                </p>
                            </div>
                        </div>
                    </label>

                    <!-- OPTION B: HARD RESET -->
                    <label class="block p-4 rounded-xl border-2 transition cursor-pointer {{ $revertStrategy === 'hard' ? 'border-rose-500 bg-rose-500/5' : 'border-border bg-canvas hover:border-rose-500/40' }}">
                        <div class="flex items-start gap-3">
                            <input type="radio" wire:model.live="revertStrategy" value="hard" class="mt-1 text-rose-600 focus:ring-rose-500">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-xs text-rose-600">Option B: Hard Reset (Destructive History Rewrite)</span>
                                    <span class="px-2 py-0.2 text-[9px] font-bold rounded-pill bg-rose-100 text-rose-800">Force Push</span>
                                </div>
                                <p class="text-xs text-rose-900 dark:text-rose-300 mt-1 leading-relaxed">
                                    Permanently rewrites branch history and force-pushes. Anyone else who has pulled this branch will have conflicts. Only use this if you are certain no one else is working off this branch.
                                </p>
                            </div>
                        </div>
                    </label>
                </div>

                <!-- Hard Reset Typed Confirmation Guard -->
                @if($revertStrategy === 'hard')
                    <div class="p-4 bg-canvas rounded-xl border border-border space-y-2 text-xs">
                        <label class="font-bold text-ink block">
                            Type <code class="px-2 py-0.5 rounded bg-surface border border-border font-mono font-bold text-rose-600 select-all">REVERT TO THIS COMMIT</code> to confirm:
                        </label>
                        <input 
                            type="text" 
                            wire:model.live="confirmRevertPhrase" 
                            placeholder="REVERT TO THIS COMMIT" 
                            class="w-full h-9 px-3.5 rounded-lg border border-border bg-surface text-ink font-mono text-xs font-bold focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500"
                        >
                        @error('confirmRevertPhrase') <span class="text-red-500 text-[10px] mt-0.5 block">{{ $message }}</span> @enderror
                    </div>
                @endif

                <!-- Modal Actions -->
                <div class="flex items-center justify-end gap-3 pt-4 border-t border-border">
                    <button 
                        type="button" 
                        wire:click="closeRevertModal" 
                        class="px-4 py-2 rounded-lg border border-border bg-canvas text-ink text-xs font-semibold hover:bg-surface transition cursor-pointer"
                    >
                        Cancel
                    </button>

                    <button 
                        type="button" 
                        wire:click="startRevert" 
                        wire:loading.attr="disabled"
                        @if($revertStrategy === 'hard' && $confirmRevertPhrase !== 'REVERT TO THIS COMMIT') disabled @endif
                        class="px-5 py-2 rounded-lg {{ $revertStrategy === 'hard' ? 'bg-rose-600 hover:bg-rose-700' : 'bg-ink hover:bg-neutral-800' }} text-white text-xs font-bold shadow-xs transition flex items-center gap-2 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                    >
                        <svg wire:loading wire:target="startRevert" class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <span>Confirm &amp; Execute {{ $revertStrategy === 'hard' ? 'Hard Reset' : 'Safe Revert' }}</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
