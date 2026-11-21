<?php

namespace App\Console\Commands;

use App\Models\MetaAdAccount;
use App\Jobs\SyncMetaAdAccount;
use Illuminate\Console\Command;

class SyncMetaAdAccounts extends Command
{
    protected $signature = 'meta:sync-ad-accounts {--account= : Connected Ad Account record ID}';
    protected $description = 'Queue independent Meta Ad Account catalogue and insights synchronization';
    public function handle(): int
    {
        MetaAdAccount::whereIn('status', ['connected', 'error', 'rate_limited'])
            ->when($this->option('account'), fn ($q, $id) => $q->whereKey($id))
            ->where(fn ($q) => $q->whereNull('retry_after')->orWhere('retry_after', '<=', now()))
            ->eachById(fn ($account) => SyncMetaAdAccount::dispatch($account->id));
        $this->info('Ad Account synchronization queued.');
        return self::SUCCESS;
    }
}
