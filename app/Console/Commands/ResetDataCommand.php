<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\User;

class ResetDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dev:reset-data {--force : Force execution even if not in local/staging}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset database tables down to Super Admin account and core roles/permissions';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (!in_array(config('app.env'), ['local', 'staging', 'testing']) && !$this->option('force')) {
            $this->error('ResetDataCommand is strictly prohibited in production environments.');
            return 1;
        }

        $databaseName = DB::getDatabaseName();
        $this->info("Starting database reset for database [{$databaseName}]...");

        Schema::disableForeignKeyConstraints();

        try {
            DB::transaction(function () use ($databaseName) {
                $driver = DB::connection()->getDriverName();

                if (in_array($driver, ['mysql', 'mariadb'])) {
                    $rawTables = DB::select(
                        "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'",
                        [$databaseName]
                    );
                    $tables = array_map(fn ($row) => array_values((array) $row)[0], $rawTables);
                } else {
                    $tables = Schema::getTableListing();
                }

                $protectedTables = [
                    'migrations',
                    'roles',
                    'permissions',
                    'model_has_roles',
                    'role_has_permissions',
                    'model_has_permissions',
                    'password_reset_tokens',
                    'sessions',
                ];

                foreach ($tables as $table) {
                    $tableName = preg_replace('/^.*\./', '', strtolower($table));

                    if (in_array($tableName, $protectedTables)) {
                        continue;
                    }

                    if ($tableName === 'users') {
                        User::where('email', '!=', 'admin@leadpanther.com')
                            ->whereDoesntHave('roles', function ($q) {
                                $q->whereIn('name', ['super-admin', 'Super Admin']);
                            })
                            ->delete();

                        $this->line("Cleared non-admin users from [users] table.");
                        continue;
                    }

                    DB::table($tableName)->delete();
                    $this->line("Cleared table [{$tableName}].");
                }
            });

            // Re-assign super-admin role to remaining admin users if needed
            $superAdmins = User::whereIn('email', ['admin@leadpanther.com'])->get();
            foreach ($superAdmins as $admin) {
                if (!$admin->hasRole('super-admin')) {
                    $admin->assignRole('super-admin');
                }
            }

            $this->info('Database reset completed successfully.');
            return 0;
        } catch (\Throwable $e) {
            $this->error('Failed to reset database: ' . $e->getMessage());
            return 1;
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }
}
