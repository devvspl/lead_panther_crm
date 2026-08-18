<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use App\Services\GitSyncService;
use Throwable;

class RunGitSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $action;
    public string $branch;
    public ?string $commitMessage;
    public ?string $followupAction;
    public ?string $targetCommitSha;
    public ?string $backupBranchName;
    public ?int $userId;

    public function __construct(
        string $action,
        string $branch = 'main',
        ?string $commitMessage = null,
        ?string $followupAction = null,
        ?int $userId = null,
        ?string $targetCommitSha = null,
        ?string $backupBranchName = null
    ) {
        $this->action = $action;
        $this->branch = $branch;
        $this->commitMessage = $commitMessage;
        $this->followupAction = $followupAction;
        $this->userId = $userId;
        $this->targetCommitSha = $targetCommitSha;
        $this->backupBranchName = $backupBranchName;
    }

    public function handle(GitSyncService $gitService): void
    {
        $userId = $this->userId ?? 0;
        $runningKey = 'git_sync_running_' . $userId;
        $resultKey = 'git_sync_result_' . $userId;

        try {
            Cache::put($runningKey, [
                'action' => $this->action,
                'branch' => $this->branch,
                'started_at' => time(),
            ], now()->addMinutes(15));

            $result = match ($this->action) {
                'pull' => $gitService->pull($this->branch, $this->userId),
                'push' => $gitService->push($this->branch, $this->commitMessage ?: 'Update from Git Sync', $this->userId),
                'revert_safe' => $gitService->safeRevert($this->branch, $this->targetCommitSha ?: 'HEAD', $this->userId),
                'revert_hard' => $gitService->hardReset($this->branch, $this->targetCommitSha ?: 'HEAD', $this->userId),
                'restore_backup' => $gitService->restoreFromBackup($this->branch, $this->backupBranchName ?: 'backup', $this->userId),
                'followup' => $gitService->runFollowupAction($this->followupAction ?: 'clear_cache', $this->userId),
                default => ['successful' => false, 'stdout' => '', 'stderr' => "Unknown action {$this->action}"],
            };

            Cache::put($resultKey, array_merge($result, [
                'action' => $this->action,
                'completed_at' => time(),
            ]), now()->addHours(1));

        } catch (Throwable $e) {
            Cache::put($resultKey, [
                'action' => $this->action,
                'successful' => false,
                'stdout' => '',
                'stderr' => 'Job execution error: ' . $e->getMessage(),
                'completed_at' => time(),
            ], now()->addHours(1));
        } finally {
            Cache::forget($runningKey);
        }
    }
}
