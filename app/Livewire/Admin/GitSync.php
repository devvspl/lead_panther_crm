<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\Attributes\Url;
use App\Livewire\Concerns\HasAdvancedTable;
use App\Services\GitSyncService;
use App\Jobs\RunGitSyncJob;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Cache;
use Throwable;

class GitSync extends Component
{
    use HasAdvancedTable;

    // Credentials & Repository Settings
    public string $remoteUrl = '';
    public string $username = '';
    public string $accessToken = '';
    public string $defaultBranch = 'main';
    public string $committerName = '';
    public string $committerEmail = '';
    public bool $hasStoredToken = false;
    public bool $isReplacingToken = false;

    // Selected Operation Branch
    public string $selectedBranch = 'main';

    // Status & Diagnostics
    public array $repoStatus = [];
    public array $remoteBranches = [];
    public ?array $connectionTestResult = null;
    public bool $isTestingConnection = false;

    // Operations State & Polling
    #[Url(as: 'tab', history: true, keep: true)]
    public string $activeTab = 'pull'; // 'pull' | 'push' | 'history' | 'settings' | 'audit'
    public bool $isSyncRunning = false;
    public ?array $lastJobResult = null;

    // Push Form & Safety Guards
    public string $commitMessage = '';
    public string $confirmPushPhrase = '';
    public ?array $secretScanResult = null;
    public bool $overrideSecretBlock = false;

    // Commit History & Revert State
    public array $commitHistory = [];
    public array $backupBranches = [];
    public bool $showRevertModal = false;
    public ?array $selectedCommit = null;
    public string $revertStrategy = 'safe'; // 'safe' | 'hard'
    public string $confirmRevertPhrase = '';

    // Follow-up Actions
    public bool $isFollowupRunning = false;
    public ?array $lastFollowupResult = null;

    public function mount(GitSyncService $gitService): void
    {
        $user = auth()->user();
        if (!$user || (!$user->hasRole('Super Admin') && !$user->hasRole('super-admin'))) {
            abort(403, 'Unauthorized. Super Admin role required for Git Deployment.');
        }

        $validTabs = ['pull', 'push', 'history', 'settings', 'audit'];
        if (!in_array($this->activeTab, $validTabs)) {
            $this->activeTab = 'pull';
        }

        $this->loadColumnPreferences();
        $this->loadSettingsAndStatus($gitService);
    }

    public function loadSettingsAndStatus(GitSyncService $gitService): void
    {
        $creds = $gitService->getCredentials();
        $this->remoteUrl = $creds['remote_url'] ?? '';
        $this->username = $creds['username'] ?? '';
        $this->defaultBranch = $creds['default_branch'] ?? 'main';
        $this->committerName = $creds['committer_name'] ?? (auth()->user()?->name ?: 'Lead Panther Deployer');
        $this->committerEmail = $creds['committer_email'] ?? (auth()->user()?->email ?: 'deploy@leadpanther.com');
        $this->selectedBranch = $this->defaultBranch;
        $this->hasStoredToken = !empty($creds['access_token']);

        $this->refreshStatus($gitService);
    }

    public function refreshStatus(GitSyncService $gitService): void
    {
        try {
            $this->repoStatus = $gitService->getRepoStatus($this->selectedBranch);
            $this->remoteBranches = $gitService->getRemoteBranches();
            if (!in_array($this->selectedBranch, $this->remoteBranches)) {
                $this->remoteBranches[] = $this->selectedBranch;
            }

            $this->commitHistory = $gitService->getCommitHistory(50);
            $this->backupBranches = $gitService->getBackupBranches();

            $userId = auth()->id() ?? 0;
            $runningKey = 'git_sync_running_' . $userId;
            $resultKey = 'git_sync_result_' . $userId;

            if (Cache::has($runningKey)) {
                $runningData = Cache::get($runningKey);
                $startedAt = is_array($runningData) ? ($runningData['started_at'] ?? 0) : 0;
                if ($startedAt > 0 && (time() - $startedAt > 60)) {
                    Cache::forget($runningKey);
                    $this->isSyncRunning = false;
                } else {
                    $this->isSyncRunning = true;
                }
            } else {
                $this->isSyncRunning = false;
            }

            if (Cache::has($resultKey)) {
                $this->lastJobResult = Cache::get($resultKey);
            }
        } catch (Throwable $e) {
            $this->repoStatus = [
                'current_branch' => 'main',
                'current_commit' => 'unknown',
                'short_commit' => 'unknown',
                'last_commit_info' => 'Unable to read git status',
                'modified_files' => [],
                'modified_count' => 0,
                'ahead_count' => 0,
                'behind_count' => 0,
                'recent_commits' => [],
                'working_directory_clean' => true,
                'upstream_configured' => false,
            ];
            $this->isSyncRunning = false;
            $this->commitHistory = [];
            $this->backupBranches = [];
        }
    }

    public function cancelSync(GitSyncService $gitService): void
    {
        $userId = auth()->id() ?? 0;
        Cache::forget('git_sync_running_' . $userId);
        $this->isSyncRunning = false;
        $this->refreshStatus($gitService);
        $this->dispatch('toast', type: 'info', message: 'Git operation lock dismissed.');
    }

    public function selectBranch(string $branch, GitSyncService $gitService): void
    {
        $this->selectedBranch = $branch;
        $this->refreshStatus($gitService);
    }

    public function setActiveTab(string $tab): void
    {
        $validTabs = ['pull', 'push', 'history', 'settings', 'audit'];
        $this->activeTab = in_array($tab, $validTabs) ? $tab : 'pull';
        $this->search = '';
        $this->statusFilter = 'all';
    }

    public function saveSettings(GitSyncService $gitService): void
    {
        $this->validate([
            'remoteUrl' => 'required|string|url|max:255',
            'username' => 'nullable|string|max:100',
            'defaultBranch' => 'required|string|max:100',
            'committerName' => 'required|string|max:100',
            'committerEmail' => 'required|email|max:150',
        ]);

        if (empty($this->accessToken) && !$this->hasStoredToken) {
            $this->addError('accessToken', 'Access Token is required to authenticate with remote Git repository.');
            return;
        }

        try {
            $gitService->saveCredentials(
                remoteUrl: $this->remoteUrl,
                username: $this->username,
                token: !empty($this->accessToken) ? $this->accessToken : null,
                defaultBranch: $this->defaultBranch,
                committerName: $this->committerName,
                committerEmail: $this->committerEmail
            );

            $this->accessToken = '';
            $this->isReplacingToken = false;
            $this->hasStoredToken = true;

            $this->dispatch('toast', type: 'success', message: 'Git deployment settings and credentials saved securely.');
            $this->refreshStatus($gitService);
        } catch (Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'Failed to save Git credentials: ' . $e->getMessage());
        }
    }

    public function testConnection(GitSyncService $gitService): void
    {
        $this->isTestingConnection = true;
        $this->connectionTestResult = null;

        $creds = $gitService->getCredentials();
        $url = $this->remoteUrl ?: $creds['remote_url'];
        $username = $this->username ?: $creds['username'];
        $token = !empty($this->accessToken) ? $this->accessToken : $creds['access_token'];

        if (empty($url) || empty($token)) {
            $this->connectionTestResult = [
                'successful' => false,
                'message' => 'Please provide both Remote URL and Access Token.',
            ];
            $this->isTestingConnection = false;
            return;
        }

        $this->connectionTestResult = $gitService->testConnection($url, $username, $token);
        $this->isTestingConnection = false;

        if ($this->connectionTestResult['successful']) {
            $this->dispatch('toast', type: 'success', message: 'Git remote connection verified successfully.');
            $this->refreshStatus($gitService);
        } else {
            $this->dispatch('toast', type: 'error', message: $this->connectionTestResult['message'] ?: 'Connection test failed. Check token permissions.');
        }
    }

    public function startPull(GitSyncService $gitService): void
    {
        $userId = auth()->id() ?? 0;

        $this->isSyncRunning = true;
        $this->lastJobResult = null;

        Cache::put('git_sync_running_' . $userId, [
            'action' => 'pull',
            'branch' => $this->selectedBranch,
            'started_at' => time(),
        ], now()->addMinutes(2));

        @set_time_limit(180);

        try {
            $result = $gitService->pull($this->selectedBranch, $userId);
            Cache::put('git_sync_result_' . $userId, array_merge($result, [
                'action' => 'pull',
                'completed_at' => time(),
            ]), now()->addHours(1));
            $this->lastJobResult = $result;

            if ($result['successful']) {
                $this->dispatch('toast', type: 'success', title: 'Pull Completed', message: "Pulled latest commits for '{$this->selectedBranch}'.");
            } else {
                $msg = $result['friendly_error'] ?? ($result['has_conflicts'] ? 'Merge conflict detected! Please resolve conflicts before pulling.' : ($result['stderr'] ?: 'Git pull encountered errors.'));
                $this->dispatch('toast', type: 'error', title: 'Pull Failed', message: $msg);
            }
        } catch (Throwable $e) {
            $this->dispatch('toast', type: 'error', title: 'Pull Error', message: $e->getMessage());
        } finally {
            Cache::forget('git_sync_running_' . $userId);
            $this->isSyncRunning = false;
            $this->refreshStatus($gitService);
        }
    }

    public function scanForSecrets(GitSyncService $gitService): void
    {
        $this->secretScanResult = $gitService->scanDiffForSecrets();
    }

    public function startPush(GitSyncService $gitService): void
    {
        $this->validate([
            'commitMessage' => 'required|string|min:4|max:255',
            'confirmPushPhrase' => 'required|in:PUSH TO PRODUCTION',
        ], [
            'confirmPushPhrase.in' => 'You must type "PUSH TO PRODUCTION" exactly to confirm pushing code to the remote repository.',
        ]);

        // Pre-push Secret Scan check
        $this->secretScanResult = $gitService->scanDiffForSecrets();
        if ($this->secretScanResult['has_secrets'] && !$this->overrideSecretBlock) {
            $this->dispatch('toast', type: 'error', title: 'Push Blocked', message: 'Potential secrets detected in changes. Review warnings before proceeding.');
            return;
        }

        $userId = auth()->id() ?? 0;
        $this->isSyncRunning = true;
        $this->lastJobResult = null;

        Cache::put('git_sync_running_' . $userId, [
            'action' => 'push',
            'branch' => $this->selectedBranch,
            'started_at' => time(),
        ], now()->addMinutes(2));

        @set_time_limit(180);

        try {
            $result = $gitService->push($this->selectedBranch, $this->commitMessage, $userId);
            Cache::put('git_sync_result_' . $userId, array_merge($result, [
                'action' => 'push',
                'completed_at' => time(),
            ]), now()->addHours(1));
            $this->lastJobResult = $result;

            if ($result['successful']) {
                $this->commitMessage = '';
                $this->confirmPushPhrase = '';
                $this->overrideSecretBlock = false;
                $this->secretScanResult = null;
                $this->dispatch('toast', type: 'success', title: 'Push Succeeded', message: "Pushed changes to origin/{$this->selectedBranch}.");
            } else {
                $msg = $result['friendly_error'] ?? ($result['stderr'] ?: 'Error pushing to remote repository.');
                $this->dispatch('toast', type: 'error', title: 'Push Failed', message: $msg);
            }
        } catch (Throwable $e) {
            $this->dispatch('toast', type: 'error', title: 'Push Error', message: $e->getMessage());
        } finally {
            Cache::forget('git_sync_running_' . $userId);
            $this->isSyncRunning = false;
            $this->refreshStatus($gitService);
        }
    }

    public function openRevertModal(string $commitHash): void
    {
        $commit = collect($this->commitHistory)->firstWhere('hash', $commitHash);
        if (!$commit) {
            $this->dispatch('toast', type: 'error', message: 'Selected commit not found in history.');
            return;
        }

        $this->selectedCommit = $commit;
        $this->revertStrategy = 'safe';
        $this->confirmRevertPhrase = '';
        $this->showRevertModal = true;
    }

    public function closeRevertModal(): void
    {
        $this->showRevertModal = false;
        $this->selectedCommit = null;
        $this->confirmRevertPhrase = '';
    }

    public function startRevert(GitSyncService $gitService): void
    {
        if (!$this->selectedCommit) {
            $this->dispatch('toast', type: 'error', message: 'No commit selected for revert.');
            return;
        }

        if ($this->revertStrategy === 'hard' && trim($this->confirmRevertPhrase) !== 'REVERT TO THIS COMMIT') {
            $this->addError('confirmRevertPhrase', 'Type "REVERT TO THIS COMMIT" exactly to confirm.');
            return;
        }

        $userId = auth()->id() ?? 0;
        $targetCommit = $this->selectedCommit['hash'];
        $actionName = $this->revertStrategy === 'hard' ? 'revert_hard' : 'revert_safe';

        $this->isSyncRunning = true;
        $this->lastJobResult = null;
        $this->showRevertModal = false;

        Cache::put('git_sync_running_' . $userId, [
            'action' => $actionName,
            'branch' => $this->selectedBranch,
            'target_commit' => $targetCommit,
            'started_at' => time(),
        ], now()->addMinutes(2));

        @set_time_limit(180);

        try {
            $result = $this->revertStrategy === 'hard'
                ? $gitService->hardReset($this->selectedBranch, $targetCommit, $userId)
                : $gitService->safeRevert($this->selectedBranch, $targetCommit, $userId);

            Cache::put('git_sync_result_' . $userId, array_merge($result, [
                'action' => $actionName,
                'completed_at' => time(),
            ]), now()->addHours(1));
            $this->lastJobResult = $result;

            if ($result['successful']) {
                $this->dispatch('toast', type: 'success', title: 'Revert Completed', message: "Codebase reverted to {$this->selectedCommit['short_hash']}. Safety backup created: {$result['backup_branch']}.");
            } else {
                $this->dispatch('toast', type: 'error', title: 'Revert Failed', message: $result['stderr'] ?: 'Revert operation encountered an error.');
            }
        } catch (Throwable $e) {
            $this->dispatch('toast', type: 'error', title: 'Revert Error', message: $e->getMessage());
        } finally {
            Cache::forget('git_sync_running_' . $userId);
            $this->isSyncRunning = false;
            $this->refreshStatus($gitService);
        }
    }

    public function restoreBackup(string $backupBranch, GitSyncService $gitService): void
    {
        $userId = auth()->id() ?? 0;
        $this->isSyncRunning = true;
        $this->lastJobResult = null;

        Cache::put('git_sync_running_' . $userId, [
            'action' => 'restore_backup',
            'branch' => $this->selectedBranch,
            'backup_branch' => $backupBranch,
            'started_at' => time(),
        ], now()->addMinutes(2));

        @set_time_limit(180);

        try {
            $result = $gitService->restoreFromBackup($this->selectedBranch, $backupBranch, $userId);
            Cache::put('git_sync_result_' . $userId, array_merge($result, [
                'action' => 'restore_backup',
                'completed_at' => time(),
            ]), now()->addHours(1));
            $this->lastJobResult = $result;

            if ($result['successful']) {
                $this->dispatch('toast', type: 'success', title: 'Backup Restored', message: "Codebase restored from backup branch '{$backupBranch}'.");
            } else {
                $this->dispatch('toast', type: 'error', title: 'Restore Failed', message: $result['stderr'] ?: 'Failed to restore codebase from backup branch.');
            }
        } catch (Throwable $e) {
            $this->dispatch('toast', type: 'error', title: 'Restore Error', message: $e->getMessage());
        } finally {
            Cache::forget('git_sync_running_' . $userId);
            $this->isSyncRunning = false;
            $this->refreshStatus($gitService);
        }
    }

    public function runFollowup(string $actionType, GitSyncService $gitService): void
    {
        $userId = auth()->id() ?? 0;
        $this->isFollowupRunning = true;
        $this->lastFollowupResult = null;

        try {
            $this->lastFollowupResult = $gitService->runFollowupAction($actionType, $userId);

            if ($this->lastFollowupResult['successful']) {
                $this->dispatch('toast', type: 'success', message: "Action '{$actionType}' executed successfully.");
            } else {
                $this->dispatch('toast', type: 'error', message: "Action '{$actionType}' encountered errors.");
            }
        } catch (Throwable $e) {
            $this->lastFollowupResult = [
                'action' => $actionType,
                'successful' => false,
                'stdout' => '',
                'stderr' => $e->getMessage(),
            ];
            $this->dispatch('toast', type: 'error', message: "Execution error: {$e->getMessage()}");
        } finally {
            $this->isFollowupRunning = false;
        }
    }

    public function checkSyncProgress(GitSyncService $gitService): void
    {
        $userId = auth()->id() ?? 0;
        $runningKey = 'git_sync_running_' . $userId;
        $resultKey = 'git_sync_result_' . $userId;

        if ($this->isSyncRunning) {
            if (!Cache::has($runningKey)) {
                $this->isSyncRunning = false;
                $this->lastJobResult = Cache::get($resultKey);
                $this->refreshStatus($gitService);
            } else {
                $runningData = Cache::get($runningKey);
                $startedAt = is_array($runningData) ? ($runningData['started_at'] ?? 0) : 0;
                if ($startedAt > 0 && (time() - $startedAt > 60)) {
                    Cache::forget($runningKey);
                    $this->isSyncRunning = false;
                    $this->refreshStatus($gitService);
                }
            }
        }
    }

    public function getTableIdentifier(): string
    {
        return 'git_sync_audit_logs';
    }

    public function tableColumns(): array
    {
        return [
            ['key' => 'id', 'label' => 'ID', 'type' => 'text', 'sortable' => true, 'priority' => 2, 'class' => 'font-mono text-muted text-[11px]'],
            ['key' => 'action', 'label' => 'Action', 'type' => 'badge', 'sortable' => true, 'priority' => 1, 'badgeStyle' => function ($val) {
                if (str_contains($val, 'push')) {
                    return 'bg-rose-50 text-rose-700 border border-rose-200';
                }
                if (str_contains($val, 'pull')) {
                    return 'bg-blue-50 text-blue-700 border border-blue-200';
                }
                if (str_contains($val, 'revert') || str_contains($val, 'hard')) {
                    return 'bg-amber-50 text-amber-700 border border-amber-200';
                }
                if (str_contains($val, 'restore')) {
                    return 'bg-purple-50 text-purple-700 border border-purple-200';
                }
                return 'bg-canvas text-ink border border-border';
            }],
            ['key' => 'user_name', 'label' => 'Performed By', 'type' => 'text', 'priority' => 1, 'class' => 'font-semibold text-ink'],
            ['key' => 'details_summary', 'label' => 'Operation Details', 'type' => 'text', 'priority' => 1, 'class' => 'font-mono text-xs text-ink max-w-md truncate'],
            ['key' => 'status_badge', 'label' => 'Status', 'type' => 'badge', 'priority' => 1, 'badgeStyle' => fn($val) => $val === 'Success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200'],
            ['key' => 'formatted_ip', 'label' => 'IP Address', 'type' => 'text', 'priority' => 2, 'class' => 'font-mono text-[11px] text-muted'],
            ['key' => 'created_at', 'label' => 'Timestamp', 'type' => 'date', 'sortable' => true, 'priority' => 1, 'format' => 'M d, Y H:i:s'],
        ];
    }

    public function quickFilters(): array
    {
        return [
            ['key' => 'all', 'label' => 'All Operations'],
            ['key' => 'push', 'label' => 'Push Events'],
            ['key' => 'pull', 'label' => 'Pull Events'],
            ['key' => 'revert', 'label' => 'Reverts & Restores'],
            ['key' => 'followup', 'label' => 'Maintenance Tasks'],
        ];
    }

    public function getFilteredAuditLogsQuery()
    {
        $query = AuditLog::with('user')
            ->where('action', 'like', 'git_%');

        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('action', 'like', '%' . $this->search . '%')
                  ->orWhere('to_value', 'like', '%' . $this->search . '%')
                  ->orWhere('from_value', 'like', '%' . $this->search . '%')
                  ->orWhere('ip_address', 'like', '%' . $this->search . '%')
                  ->orWhereHas('user', fn($uq) => $uq->where('name', 'like', '%' . $this->search . '%'));
            });
        }

        if ($this->statusFilter === 'push') {
            $query->where('action', 'like', '%push%');
        } elseif ($this->statusFilter === 'pull') {
            $query->where('action', 'like', '%pull%');
        } elseif ($this->statusFilter === 'revert') {
            $query->where(function ($q) {
                $q->where('action', 'like', '%revert%')
                  ->orWhere('action', 'like', '%restore%');
            });
        } elseif ($this->statusFilter === 'followup') {
            $query->where('action', 'like', '%followup%');
        }

        if (!empty($this->sortField)) {
            $query->orderBy($this->sortField, $this->sortDirection);
        } else {
            $query->latest('id');
        }

        return $query;
    }

    public function commitTableColumns(): array
    {
        return [
            [
                'key' => 'short_hash',
                'label' => 'Commit',
                'render' => function ($row) {
                    $short = is_array($row) ? ($row['short_hash'] ?? '') : ($row->short_hash ?? '');
                    $isHead = ($this->repoStatus['short_commit'] ?? '') === $short;
                    $dot = $isHead ? '<span class="w-2 h-2 rounded-full bg-emerald-500 shrink-0" title="Current HEAD"></span>' : '';
                    return '<div class="font-mono font-bold text-primary flex items-center gap-1.5">' . $dot . '<span>' . e($short) . '</span></div>';
                },
                'sortable' => false,
                'priority' => 1,
            ],
            [
                'key' => 'message',
                'label' => 'Commit Message',
                'render' => fn($row) => '<div class="font-semibold text-ink max-w-lg truncate" title="' . e(is_array($row) ? ($row['message'] ?? '') : ($row->message ?? '')) . '">' . e(is_array($row) ? ($row['message'] ?? '') : ($row->message ?? '')) . '</div>',
                'sortable' => false,
                'priority' => 1,
            ],
            [
                'key' => 'author',
                'label' => 'Author',
                'render' => fn($row) => '<span class="text-xs text-ink font-medium">' . e(is_array($row) ? ($row['author'] ?? '') : ($row->author ?? '')) . '</span>',
                'sortable' => false,
                'priority' => 2,
            ],
            [
                'key' => 'date',
                'label' => 'Date',
                'render' => function ($row) {
                    $rawDate = is_array($row) ? ($row['date'] ?? '') : ($row->date ?? '');
                    try {
                        $formatted = \Carbon\Carbon::parse($rawDate)->diffForHumans();
                    } catch (\Throwable $e) {
                        $formatted = $rawDate;
                    }
                    return '<span class="text-muted font-mono text-[11px]">' . e($formatted) . '</span>';
                },
                'sortable' => false,
                'priority' => 2,
            ],
            [
                'key' => 'action',
                'label' => 'Action',
                'align' => 'right',
                'render' => fn($row) => '<div class="flex items-center justify-end"><button type="button" wire:click="openRevertModal(\'' . (is_array($row) ? ($row['hash'] ?? '') : ($row->hash ?? '')) . '\')" class="px-2.5 py-1 rounded-lg border border-border bg-canvas text-ink text-xs font-semibold hover:border-danger hover:text-danger hover:bg-danger/5 transition shadow-2xs inline-flex items-center gap-1.5 cursor-pointer"><svg class="w-3 h-3 text-danger" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12.066 11.2a1 1 0 000 1.6l5.334 4A1 1 0 0019 16V8a1 1 0 00-1.6-.8l-5.334 4zM4.066 11.2a1 1 0 000 1.6l5.334 4A1 1 0 0011 16V8a1 1 0 00-1.6-.8l-5.334 4z"/></svg><span>Revert to here</span></button></div>',
                'sortable' => false,
                'priority' => 1,
            ],
        ];
    }

    public function historyQuickFilters(): array
    {
        return [
            ['key' => 'all', 'label' => 'All (Last 50)', 'count' => count($this->commitHistory)],
            ['key' => 'recent_10', 'label' => 'Recent 10', 'count' => min(10, count($this->commitHistory))],
            ['key' => 'my_commits', 'label' => 'My Commits'],
        ];
    }

    public function getFilteredCommitHistoryProperty(): array
    {
        $commits = $this->commitHistory;

        if (!empty($this->search) && $this->activeTab === 'history') {
            $search = strtolower(trim($this->search));
            $commits = array_filter($commits, function ($c) use ($search) {
                return str_contains(strtolower($c['hash'] ?? ''), $search)
                    || str_contains(strtolower($c['short_hash'] ?? ''), $search)
                    || str_contains(strtolower($c['message'] ?? ''), $search)
                    || str_contains(strtolower($c['author'] ?? ''), $search);
            });
        }

        if ($this->activeTab === 'history') {
            if ($this->statusFilter === 'recent_10') {
                $commits = array_slice($commits, 0, 10);
            } elseif ($this->statusFilter === 'my_commits') {
                $user = auth()->user();
                $author = strtolower(trim($this->committerName ?: ($user?->name ?: '')));
                if (!empty($author)) {
                    $commits = array_filter($commits, fn($c) => str_contains(strtolower($c['author'] ?? ''), $author));
                }
            }
        }

        return array_values($commits);
    }

    public function render()
    {
        $gitAuditLogs = $this->getFilteredAuditLogsQuery()->paginate($this->perPage);

        return view('livewire.admin.git-sync', [
            'gitAuditLogs' => $gitAuditLogs,
            'filteredCommitHistory' => $this->filteredCommitHistory,
        ])->layout('layouts.app');
    }
}
