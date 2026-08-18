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

            $this->isSyncRunning = Cache::has($runningKey);
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
            ];
            $this->commitHistory = [];
            $this->backupBranches = [];
        }
    }

    public function saveSettings(GitSyncService $gitService): void
    {
        $this->validate([
            'remoteUrl' => 'required|url',
            'username' => 'nullable|string|max:100',
            'accessToken' => $this->hasStoredToken && !$this->isReplacingToken ? 'nullable' : 'required|string',
            'defaultBranch' => 'required|string|max:50',
        ]);

        $gitService->saveCredentials(
            remoteUrl: $this->remoteUrl,
            username: $this->username,
            token: $this->accessToken ?: null,
            defaultBranch: $this->defaultBranch
        );

        $this->hasStoredToken = true;
        $this->isReplacingToken = false;
        $this->accessToken = '';
        $this->selectedBranch = $this->defaultBranch;

        $this->dispatch('toast', type: 'success', message: 'Git repository settings saved encrypted.');
        $this->refreshStatus($gitService);
    }

    public function testConnection(GitSyncService $gitService): void
    {
        $this->isTestingConnection = true;
        $this->connectionTestResult = null;

        $creds = $gitService->getCredentials();
        $token = $this->accessToken ?: $creds['access_token'];
        $username = $this->username ?: $creds['username'];
        $url = $this->remoteUrl ?: $creds['remote_url'];

        if (empty($token) || empty($url)) {
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
            $this->dispatch('toast', type: 'error', message: 'Connection test failed. Check token permissions.');
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
        ], now()->addMinutes(15));

        // If queue driver is sync, execute directly
        if (config('queue.default') === 'sync') {
            $result = $gitService->pull($this->selectedBranch, $userId);
            Cache::put('git_sync_result_' . $userId, array_merge($result, [
                'action' => 'pull',
                'completed_at' => time(),
            ]), now()->addHours(1));
            Cache::forget('git_sync_running_' . $userId);
            $this->isSyncRunning = false;
            $this->lastJobResult = $result;

            if ($result['successful']) {
                $this->dispatch('toast', type: 'success', title: 'Pull Completed', message: "Pulled latest commits for '{$this->selectedBranch}'.");
            } else {
                $this->dispatch('toast', type: 'error', title: 'Pull Failed', message: $result['has_conflicts'] ? 'Merge conflict detected!' : 'Git pull encountered errors.');
            }
            $this->refreshStatus($gitService);
        } else {
            RunGitSyncJob::dispatch('pull', $this->selectedBranch, null, null, $userId);
            $this->dispatch('toast', type: 'info', message: "Pull job queued for branch '{$this->selectedBranch}'.");
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
        ], now()->addMinutes(15));

        if (config('queue.default') === 'sync') {
            $result = $gitService->push($this->selectedBranch, $this->commitMessage, $userId);
            Cache::put('git_sync_result_' . $userId, array_merge($result, [
                'action' => 'push',
                'completed_at' => time(),
            ]), now()->addHours(1));
            Cache::forget('git_sync_running_' . $userId);
            $this->isSyncRunning = false;
            $this->lastJobResult = $result;

            if ($result['successful']) {
                $this->commitMessage = '';
                $this->confirmPushPhrase = '';
                $this->overrideSecretBlock = false;
                $this->secretScanResult = null;
                $this->dispatch('toast', type: 'success', title: 'Push Succeeded', message: "Pushed changes to origin/{$this->selectedBranch}.");
            } else {
                $this->dispatch('toast', type: 'error', title: 'Push Failed', message: 'Error pushing to remote repository.');
            }
            $this->refreshStatus($gitService);
        } else {
            RunGitSyncJob::dispatch('push', $this->selectedBranch, $this->commitMessage, null, $userId);
            $this->dispatch('toast', type: 'info', message: "Push job queued for branch '{$this->selectedBranch}'.");
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

        if ($this->revertStrategy === 'hard') {
            $this->validate([
                'confirmRevertPhrase' => 'required|in:REVERT TO THIS COMMIT',
            ], [
                'confirmRevertPhrase.in' => 'You must type "REVERT TO THIS COMMIT" exactly to confirm hard reset.',
            ]);
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
        ], now()->addMinutes(15));

        if (config('queue.default') === 'sync') {
            $result = $this->revertStrategy === 'hard'
                ? $gitService->hardReset($this->selectedBranch, $targetCommit, $userId)
                : $gitService->safeRevert($this->selectedBranch, $targetCommit, $userId);

            Cache::put('git_sync_result_' . $userId, array_merge($result, [
                'action' => $actionName,
                'completed_at' => time(),
            ]), now()->addHours(1));
            Cache::forget('git_sync_running_' . $userId);
            $this->isSyncRunning = false;
            $this->lastJobResult = $result;

            if ($result['successful']) {
                $this->dispatch('toast', type: 'success', title: 'Revert Completed', message: "Codebase reverted to {$this->selectedCommit['short_hash']}. Safety backup created: {$result['backup_branch']}.");
            } else {
                $this->dispatch('toast', type: 'error', title: 'Revert Failed', message: $result['stderr'] ?: 'Revert operation encountered an error.');
            }
            $this->refreshStatus($gitService);
        } else {
            RunGitSyncJob::dispatch(
                action: $actionName,
                branch: $this->selectedBranch,
                userId: $userId,
                targetCommitSha: $targetCommit
            );
            $this->dispatch('toast', type: 'info', message: "Revert job queued for commit {$this->selectedCommit['short_hash']}.");
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
        ], now()->addMinutes(15));

        if (config('queue.default') === 'sync') {
            $result = $gitService->restoreFromBackup($this->selectedBranch, $backupBranch, $userId);
            Cache::put('git_sync_result_' . $userId, array_merge($result, [
                'action' => 'restore_backup',
                'completed_at' => time(),
            ]), now()->addHours(1));
            Cache::forget('git_sync_running_' . $userId);
            $this->isSyncRunning = false;
            $this->lastJobResult = $result;

            if ($result['successful']) {
                $this->dispatch('toast', type: 'success', title: 'Backup Restored', message: "Codebase restored from backup branch '{$backupBranch}'.");
            } else {
                $this->dispatch('toast', type: 'error', title: 'Restore Failed', message: 'Failed to restore codebase from backup branch.');
            }
            $this->refreshStatus($gitService);
        } else {
            RunGitSyncJob::dispatch(
                action: 'restore_backup',
                branch: $this->selectedBranch,
                userId: $userId,
                backupBranchName: $backupBranch
            );
            $this->dispatch('toast', type: 'info', message: "Restore job queued for '{$backupBranch}'.");
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

        if ($this->isSyncRunning && !Cache::has($runningKey)) {
            $this->isSyncRunning = false;
            $this->lastJobResult = Cache::get($resultKey);
            $this->refreshStatus($gitService);
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
