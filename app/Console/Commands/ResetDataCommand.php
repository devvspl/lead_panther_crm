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
    protected $signature = 'dev:reset-data {--force : Force execution even if not in local/staging} {--keep-required : Keep required lookups, lead sources, settings, and platform organization}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset database tables down to Super Admin account and core roles/permissions';

    /**
     * Execute the console command.
     */
    public function handle(\App\Services\DatabaseCleanupService $cleanupService): int
    {
        if (!in_array(config('app.env'), ['local', 'staging', 'testing']) && !$this->option('force')) {
            $this->error('ResetDataCommand is strictly prohibited in production environments.');
            return 1;
        }

        if ($this->option('keep-required')) {
            $this->info('Cleaning dummy data while keeping required configuration and Super Admin...');
            $result = $cleanupService->clean();
            $this->info("Completed. Preserved {$result['super_admins_preserved']} super admin(s). Cleared {$result['records_removed']} dummy records across {$result['tables_cleaned']} tables.");
            return 0;
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

            // Ensure admin@leadpanther.com exists with ID 1 and has Super Admin role
            $admin = User::where('email', 'admin@leadpanther.com')->orWhere('id', 1)->first();
            if (!$admin) {
                DB::table('users')->insert([
                    'id' => 1,
                    'name' => 'Super Admin',
                    'email' => 'admin@leadpanther.com',
                    'password' => bcrypt('password'),
                    'phone' => '+919800000001',
                    'status' => 'active',
                    'email_verified_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $admin = User::find(1);
            } elseif ($admin->id !== 1 && !User::where('id', 1)->exists()) {
                $oldId = $admin->id;
                DB::table('users')->where('id', $oldId)->update(['id' => 1]);
                DB::table('model_has_roles')
                    ->where('model_type', User::class)
                    ->where('model_id', $oldId)
                    ->update(['model_id' => 1]);
                $admin = User::find(1);
            }

            if ($admin) {
                $canonicalRole = \Spatie\Permission\Models\Role::whereIn('name', ['Super Admin', 'super-admin'])->first();
                if ($canonicalRole && !$admin->hasRole($canonicalRole->name)) {
                    $admin->assignRole($canonicalRole->name);
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
