<?php

namespace App\Jobs;

use App\Models\MetaAdRecord;
use App\Services\MetaCampaignLeadService;
use App\Services\MetaGraphException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class FetchMetaCampaignLeads implements ShouldQueue
{
    use Queueable;

    public int $tries = 8;
    public int $timeout = 60;

    public function __construct(public int $recordId, public string $runId) {}
    public function backoff(): array { return [60, 300, 900, 1800]; }
    public function middleware(): array
    {
        return [(new WithoutOverlapping('meta-campaign-leads-'.$this->recordId))->releaseAfter(5)->expireAfter(75)];
    }

    public function handle(MetaCampaignLeadService $service): void
    {
        $record = MetaAdRecord::where('kind', 'campaign')->find($this->recordId);
        if (!$record || data_get($record->lead_fetch_state, 'run_id') !== $this->runId) return;
        try {
            if ($service->tick($record)) self::dispatch($record->id, $this->runId)->delay(now()->addSeconds(2));
        } catch (MetaGraphException $e) {
            if ($e->retryable && $this->job && $this->attempts() < $this->tries) {
                $this->release($e->retryAfter);
                return;
            }
            throw $e;
        }
    }

    public function failed(?\Throwable $exception): void
    {
        if ($record = MetaAdRecord::find($this->recordId)) {
            $state = $record->lead_fetch_state ?? [];
            if (($state['run_id'] ?? null) !== $this->runId) return;
            $state['status'] = 'failed';
            $state['error'] ??= 'Queue retries exhausted. Check Page access and retry Fetch Leads to resume.';
            $record->update(['lead_fetch_state' => $state]);
        }
    }
}
