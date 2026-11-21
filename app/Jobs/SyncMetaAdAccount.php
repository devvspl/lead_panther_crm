<?php

namespace App\Jobs;

use App\Models\MetaAdAccount;
use App\Services\MetaAdAccountService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class SyncMetaAdAccount implements ShouldQueue
{
    use Queueable;
    public int $tries = 4;
    public int $timeout = 60;
    public function __construct(public int $accountId) {}
    public function backoff(): array { return [300, 900, 1800]; }
    public function middleware(): array { return [(new WithoutOverlapping('meta-ad-job-'.$this->accountId))->dontRelease()->expireAfter(75)]; }
    public function handle(MetaAdAccountService $service): void
    {
        if ($account = MetaAdAccount::find($this->accountId)) {
            $service->sync($account);
            $account->refresh();
            if ($account->sync_state && $account->status === 'connected') self::dispatch($account->id)->delay(now()->addSeconds(2));
        }
    }
}
