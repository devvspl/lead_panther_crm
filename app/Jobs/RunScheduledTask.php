<?php
namespace App\Jobs;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
class RunScheduledTask implements ShouldQueue {
    use Queueable;
    public int $tries=1;
    public int $timeout=300;
    public function __construct(public int $runId) { $this->afterCommit(); }
    public function handle(\App\Services\ScheduledTaskService $service): void { $service->runManual($this->runId); }
    public function failed(?\Throwable $error): void { app(\App\Services\ScheduledTaskService::class)->failRun($this->runId,$error?->getMessage() ?: 'Queue worker stopped this task.'); }
}
