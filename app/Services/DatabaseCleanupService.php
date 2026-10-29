<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use App\Models\Organization;
use App\Models\LeadSource;
use Spatie\Permission\Models\Role;

class DatabaseCleanupService
{
    /**
     * Tables containing critical system configuration and metadata that must NEVER be purged.
     */
    public const PROTECTED_TABLES = [
        'migrations',
        'roles',
        'permissions',
        'role_has_permissions',
        'model_has_permissions',
        'sessions',
        'password_reset_tokens',
        'general_settings',
        'lead_sources',
        'replacement_reasons',
        'credit_packages',
        'notification_templates',
        'portal_accounts',
        'integration_credentials',
    ];

    /**
     * Tables holding dummy/demo transactional data to be purged.
     */
    public const DUMMY_DATA_TABLES = [
        'leads',
        'lead_assignments',
        'lead_communications',
        'lead_replacements',
        'lead_status_history',
        'lead_calls',
        'lead_emails',
        'lead_messages',
        'lead_metadata',
        'lead_form_mappings',
        'followups',
        'meetings',
        'site_visits',
        'proposals',
        'negotiations',
        'bookings',
        'payments',
        'payments_received',
        'invoices',
        'projects',
        'project_units',
        'campaigns',
        'campaign_sources',
        'client_wallets',
        'channel_partners',
        'credit_transactions',
        'recharge_requests',
        'distribution_rules',
        'sales_teams',
        'sales_team_members',
        'audit_logs',
        'notifications',
        'notification_logs',
        'api_logs',
        'webhook_logs',
        'jobs',
        'failed_jobs',
        'job_batches',
        'upload_batches',
    ];

    /**
     * Clean all dummy/demo data while strictly preserving required system configuration,
     * Super Admin accounts, roles, permissions, lead sources, and platform organization.
     *
     * @return array Summary of cleaned and preserved entities
     */
    public function clean(): array
    {
        $databaseName = DB::getDatabaseName();
        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'])) {
            $rawTables = DB::select(
                "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'",
                [$databaseName]
            );
            $availableTables = array_map(fn ($row) => array_values((array) $row)[0], $rawTables);
        } else {
            $availableTables = Schema::getTableListing();
        }

        $availableTables = array_map(fn ($tbl) => preg_replace('/^.*\./', '', strtolower($tbl)), $availableTables);

        Schema::disableForeignKeyConstraints();

        $recordsRemoved = 0;
        $tablesCleaned = 0;
        $superAdminCount = 0;

        try {
            DB::transaction(function () use ($availableTables, &$recordsRemoved, &$tablesCleaned, &$superAdminCount) {
                // 1. Identify and guarantee Super Admin user account (admin@leadpanther.com with ID 1)
                $adminUser = User::where('email', 'admin@leadpanther.com')->orWhere('id', 1)->first();
                if (!$adminUser) {
                    // Always guarantee admin@leadpanther.com with ID 1 exists
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
                    $adminUser = User::find(1);
                } elseif ($adminUser->id !== 1 && !User::where('id', 1)->exists()) {
                    // Restore ID to 1 if it drifted
                    $oldId = $adminUser->id;
                    DB::table('users')->where('id', $oldId)->update(['id' => 1]);
                    if (in_array('model_has_roles', $availableTables)) {
                        DB::table('model_has_roles')
                            ->where('model_type', User::class)
                            ->where('model_id', $oldId)
                            ->update(['model_id' => 1]);
                    }
                    $adminUser = User::find(1);
                }

                $superAdmins = User::where('email', 'admin@leadpanther.com')
                    ->orWhere('id', 1)
                    ->orWhereHas('roles', function ($q) {
                        $q->whereIn('name', ['Super Admin', 'super-admin']);
                    })
                    ->get();

                $superAdminIds = array_unique(array_merge([1], $superAdmins->pluck('id')->filter()->toArray()));
                $superAdminCount = count($superAdminIds);

                // 2. Clear all dummy transactional tables
                foreach (self::DUMMY_DATA_TABLES as $table) {
                    if (in_array($table, $availableTables)) {
                        $count = DB::table($table)->count();
                        if ($count > 0) {
                            DB::table($table)->delete();
                            $recordsRemoved += $count;
                            $tablesCleaned++;
                        }
                    }
                }

                // 3. Clear non-admin users (strictly protecting admin@leadpanther.com and ID 1)
                if (in_array('users', $availableTables)) {
                    $deletedUsersCount = User::where('email', '!=', 'admin@leadpanther.com')
                        ->where('id', '!=', 1)
                        ->whereNotIn('id', $superAdminIds)
                        ->delete();

                    $recordsRemoved += $deletedUsersCount;
                    if ($deletedUsersCount > 0) {
                        $tablesCleaned++;
                    }

                    // Clean model_has_roles for removed users
                    if (in_array('model_has_roles', $availableTables)) {
                        DB::table('model_has_roles')->whereNotIn('model_id', $superAdminIds)->delete();
                    }
                }

                // 4. Preserve or establish platform organization & link Super Admin
                if (in_array('organizations', $availableTables)) {
                    $platformOrg = Organization::firstOrCreate(
                        ['name' => 'Lead Panther Platform HQ'],
                        ['status' => 'active']
                    );

                    $deletedOrgsCount = Organization::where('id', '!=', $platformOrg->id)->delete();
                    $recordsRemoved += $deletedOrgsCount;
                    if ($deletedOrgsCount > 0) {
                        $tablesCleaned++;
                    }

                    // Ensure all preserved super admins point to the valid platform org
                    if (!empty($superAdminIds)) {
                        User::whereIn('id', $superAdminIds)->update(['organization_id' => $platformOrg->id]);
                    }
                }

                // 5. Ensure core master lookup tables are populated if empty
                if (in_array('lead_sources', $availableTables) && DB::table('lead_sources')->count() === 0) {
                    $sources = ['meta', 'google', 'portal', 'owned_portal', 'manual'];
                    foreach ($sources as $src) {
                        LeadSource::firstOrCreate(['name' => $src], ['is_active' => true]);
                    }
                }

                if (in_array('replacement_reasons', $availableTables) && DB::table('replacement_reasons')->count() === 0) {
                    DB::table('replacement_reasons')->insert([
                        ['label' => 'Invalid Phone Number', 'is_eligible' => 1, 'requires_sla_check' => 1, 'created_at' => now(), 'updated_at' => now()],
                        ['label' => 'Out of Budget', 'is_eligible' => 1, 'requires_sla_check' => 0, 'created_at' => now(), 'updated_at' => now()],
                        ['label' => 'Duplicate Lead', 'is_eligible' => 1, 'requires_sla_check' => 1, 'created_at' => now(), 'updated_at' => now()],
                        ['label' => 'Wrong Location Preference', 'is_eligible' => 0, 'requires_sla_check' => 0, 'created_at' => now(), 'updated_at' => now()],
                    ]);
                }

                if (in_array('credit_packages', $availableTables) && DB::table('credit_packages')->count() === 0) {
                    DB::table('credit_packages')->insert([
                        ['name' => 'Starter Pack', 'credit_count' => 100, 'price' => 5000.00, 'validity_days' => 30, 'created_at' => now(), 'updated_at' => now()],
                        ['name' => 'Growth Pack', 'credit_count' => 500, 'price' => 22000.00, 'validity_days' => 60, 'created_at' => now(), 'updated_at' => now()],
                        ['name' => 'Pro Pack', 'credit_count' => 1200, 'price' => 50000.00, 'validity_days' => 90, 'created_at' => now(), 'updated_at' => now()],
                        ['name' => 'Enterprise Pack', 'credit_count' => 3000, 'price' => 110000.00, 'validity_days' => 120, 'created_at' => now(), 'updated_at' => now()],
                    ]);
                }

                // 6. Ensure Super Admin role assignment
                $roleWeb = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
                Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

                foreach ($superAdmins as $admin) {
                    if (!$admin->hasRole('Super Admin') && !$admin->hasRole('super-admin')) {
                        $admin->assignRole('Super Admin');
                    }
                }

                $primaryAdmin = User::find(1);
                if ($primaryAdmin && !$primaryAdmin->hasRole('Super Admin') && !$primaryAdmin->hasRole('super-admin')) {
                    $primaryAdmin->assignRole('Super Admin');
                }

                // 7. Clear caches
                if (in_array('cache', $availableTables)) {
                    DB::table('cache')->truncate();
                }
                if (in_array('cache_locks', $availableTables)) {
                    DB::table('cache_locks')->truncate();
                }

                // 8. Log audit trail
                if (in_array('audit_logs', $availableTables)) {
                    try {
                        DB::table('audit_logs')->insert([
                            'user_id' => auth()->id() ?? ($superAdminIds[0] ?? null),
                            'action' => 'database_dummy_data_cleaned',
                            'subject_type' => 'Database',
                            'subject_id' => 0,
                            'from_value' => null,
                            'to_value' => json_encode([
                                'status' => 'retained_required_only',
                                'cleaned_at' => now()->toIso8601String(),
                                'tables_cleaned' => $tablesCleaned,
                                'records_removed' => $recordsRemoved,
                            ]),
                            'ip_address' => request()?->ip(),
                            'user_agent' => request()?->userAgent(),
                            'created_at' => now(),
                        ]);
                    } catch (\Throwable $e) {
                        // Fail open on audit log insertion
                    }
                }
            });

            return [
                'successful' => true,
                'super_admins_preserved' => $superAdminCount,
                'organizations_preserved' => 1,
                'tables_cleaned' => $tablesCleaned,
                'records_removed' => $recordsRemoved,
                'protected_tables' => self::PROTECTED_TABLES,
            ];
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }
}
