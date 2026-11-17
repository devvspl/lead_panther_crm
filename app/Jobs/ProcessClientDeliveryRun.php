<?php
namespace App\Jobs;

use App\Services\LeadDistributionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessClientDeliveryRun implements ShouldQueue
{
    use Queueable;
    public int $tries = 3;
    public int $timeout = 120;
    public function __construct(public int $runId) { $this->afterCommit(); }
    public function backoff(): array { return [30, 120, 300]; }
    public function handle(LeadDistributionService $service): void
    {
        if ($service->processClientDeliveryRun($this->runId)) self::dispatch($this->runId)->delay(now()->addSecond());
    }
}
