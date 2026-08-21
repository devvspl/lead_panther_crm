<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Services\GitSyncService;
use App\Jobs\RunGitSyncJob;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Cache;
use Throwable;

class GitSync extends Component
{
    // Credentials & Repository Settings
    public string $remoteUrl = '';
    public string $username = '';
    public string $accessToken = '';
    public string $defaultBranch = 'main';
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

        $this->loadSettingsAndStatus($gitService);
    }

    public function loadSettingsAndStatus(GitSyncService $gitService): void
    {
        $creds = $gitService->getCredentials();
        $this->remoteUrl = $creds['remote_url'] ?: '';
        $this->username = $creds['username'] ?: '';
        $this->defaultBranch = $creds['default_branch'] ?: 'main';
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
        $this->activeTab = $tab;
    }

    public function saveSettings(GitSyncService $gitService): void
    {
        $this->validate([
            'remoteUrl' => 'required|string|url|max:255',
            'username' => 'nullable|string|max:100',
            'defaultBranch' => 'required|string|max:100',
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
                defaultBranch: $this->defaultBranch
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
                $this->dispatch('toast', type: 'error', title: 'Pull Failed', message: $result['has_conflicts'] ? 'Merge conflict detected!' : ($result['stderr'] ?: 'Git pull encountered errors.'));
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
                $this->dispatch('toast', type: 'error', title: 'Push Failed', message: $result['stderr'] ?: 'Error pushing to remote repository.');
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

    public function render()
    {
        $gitAuditLogs = AuditLog::with('user')
            ->where('action', 'like', 'git_%')
            ->latest('id')
            ->take(15)
            ->get();

        return view('livewire.admin.git-sync', [
            'gitAuditLogs' => $gitAuditLogs,
        ])->layout('layouts.app');
    }
}
