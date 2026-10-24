<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DatabaseCleanupService;

class CleanDummyDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dev:clean-dummy-data {--force : Bypass environment checks and confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove all dummy/demo data from the database while keeping strictly required configuration, lead sources, roles, and Super Admin accounts';

    /**
     * Execute the console command.
     */
    public function handle(DatabaseCleanupService $cleanupService): int
    {
        $env = config('app.env');

        if (!in_array($env, ['local', 'staging', 'testing']) && !$this->option('force')) {
            $this->error("Cleaning dummy data is strictly guarded in [{$env}] environments. Use --force if you are certain.");
            return 1;
        }

        if (!$this->option('force') && $this->input->isInteractive()) {
            if (!$this->confirm('This will purge all demo leads, campaigns, clients, projects, demo users, and transactions while keeping required system settings and Super Admin accounts. Do you wish to continue?', false)) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        $this->info('Starting dummy data removal (preserving required data only)...');

        try {
            $result = $cleanupService->clean();

            $this->newLine();
            $this->info('Dummy data removal completed successfully!');
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Super Admins Preserved', $result['super_admins_preserved']],
                    ['Platform Organization Preserved', $result['organizations_preserved']],
                    ['Tables Cleaned', $result['tables_cleaned']],
                    ['Total Records Purged', number_format($result['records_removed'])],
                ]
            );

            $this->line('<fg=cyan>Retained Required Tables:</> ' . implode(', ', $result['protected_tables']));

            return 0;
        } catch (\Throwable $e) {
            $this->error('Failed to clean dummy data: ' . $e->getMessage());
            return 1;
        }
    }
}
