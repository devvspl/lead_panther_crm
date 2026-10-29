<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PortalAccount;
use App\Services\MetaLeadSyncService;

class SyncMetaLeadsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'meta:sync-leads {--account= : Specific PortalAccount ID to sync} {--limit=100 : Max leads to pull per form}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically sync all Facebook/Meta Lead Ads forms and leads into the CRM';

    /**
     * Execute the console command.
     */
    public function handle(MetaLeadSyncService $syncService): int
    {
        $accountId = $this->option('account');
        $limit = (int) ($this->option('limit') ?: 100);

        $query = PortalAccount::query()->where('type', 'meta')->where('status', 'active');
        if ($accountId) {
            $query->where('id', $accountId);
        }

        $accounts = $query->get();

        if ($accounts->isEmpty()) {
            $this->info("No active Meta portal accounts found to sync.");
            return Command::SUCCESS;
        }

        $this->info("Starting automatic Meta Leads sync across {$accounts->count()} account(s)...");

        $grandTotalCreated = 0;
        $grandTotalFetched = 0;

        foreach ($accounts as $account) {
            $this->line("Processing Account #{$account->id} ({$account->name})...");

            $result = $syncService->sync($account, null, $limit);

            $this->line("  -> Result: {$result['message']}");

            foreach ($result['forms'] as $f) {
                if (($f['status'] ?? '') === 'success') {
                    $this->info("     ✓ {$f['form_name']}: {$f['created']} created, {$f['duplicates']} duplicates");
                } else {
                    $this->warn("     ✗ {$f['form_name']}: " . ($f['error'] ?? 'Unknown error'));
                }
            }

            $grandTotalCreated += $result['total_created'];
            $grandTotalFetched += $result['total_fetched'];
        }

        $this->info("Meta sync complete: {$grandTotalCreated} new leads imported ({$grandTotalFetched} total records checked).");

        return Command::SUCCESS;
    }
}

