<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Organization;
use App\Models\User;
use App\Models\Project;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\SalesTeam;

class RemoveNrrDummyDataSeeder extends Seeder
{
    /**
     * Target Organization details.
     */
    public const TARGET_ORG_ID = 34;
    public const TARGET_ORG_NAME = 'NRR';

    /**
     * Known dummy user emails created by Organization34SampleSeeder.
     */
    public const DUMMY_EMAILS = [
        'nrr@leadpanther.crm',
        'aman.sharma@nrrbuildcon.com',
        'priya.patel@nrrbuildcon.com',
        'vikram.malhotra@nrrbuildcon.com',
        'sneha.reddy@nrrbuildcon.com',
    ];

    /**
     * Execute the NRR dummy data removal.
     */
    public function run(): void
    {
        $isDryRun = filter_var(env('NRR_CLEANUP_DRY_RUN', false), FILTER_VALIDATE_BOOLEAN)
            || in_array('--dry-run', $_SERVER['argv'] ?? []);

        $this->command?->info("================================================================================");
        $this->command?->info(" [NRR DUMMY DATA CLEANUP SEEDER] " . ($isDryRun ? "(DRY-RUN MODE)" : "(EXECUTION MODE)"));
        $this->command?->info("================================================================================");

        // 1. Verify Master Organization 34 exists and is protected
        $org = Organization::find(self::TARGET_ORG_ID);
        if (!$org) {
            $this->command?->warn("Target Organization ID " . self::TARGET_ORG_ID . " not found in database.");
            return;
        }

        if (strtoupper($org->name) !== self::TARGET_ORG_NAME) {
            $this->command?->error("Organization ID " . self::TARGET_ORG_ID . " name mismatch: '{$org->name}' vs expected '" . self::TARGET_ORG_NAME . "'. Aborting for safety.");
            return;
        }

        $this->command?->info("✓ Master Organization identified: ID {$org->id} ({$org->name}) — [PROTECTED FROM DELETION]");

        // 2. Identify NRR Dummy Records via verified relationships
        $scope = $this->resolveNrrDummyScope();

        // Check if there are any dummy records
        $totalDummyCount = array_sum(array_map('count', [
            $scope['users'],
            $scope['projects'],
            $scope['campaigns'],
            $scope['leads'],
            $scope['sales_teams'],
        ]));

        if ($totalDummyCount === 0) {
            $this->command?->info("✓ No dummy records found for Organization 34. Database is already clean.");
            return;
        }

        // 3. Dry-Run or Execute
        if ($isDryRun) {
            $this->generateReport($scope, true);
            $this->command?->info("\n[DRY-RUN COMPLETED] No records were modified or deleted.");
            $this->command?->info("Run without DRY_RUN to permanently remove these records:");
            $this->command?->info("  php artisan db:seed --class=RemoveNrrDummyDataSeeder\n");
            return;
        }

        // 4. Permanent Cleanup inside DB Transaction
        $deletedCounts = [];

        DB::transaction(function () use ($scope, &$deletedCounts) {
            $userIds = $scope['users'];
            $projectIds = $scope['projects'];
            $campaignIds = $scope['campaigns'];
            $leadIds = $scope['leads'];
            $salesTeamIds = $scope['sales_teams'];

            // -------------------------------------------------------------
            // Stage 1: Lead Dependencies (Leaves of the hierarchy)
            // -------------------------------------------------------------
            if (!empty($leadIds)) {
                $deletedCounts['lead_assignments'] = DB::table('lead_assignments')->whereIn('lead_id', $leadIds)->delete();
                $deletedCounts['lead_replacements'] = DB::table('lead_replacements')->whereIn('lead_id', $leadIds)->delete();
                $deletedCounts['followups'] = DB::table('followups')->whereIn('lead_id', $leadIds)->delete();
                $deletedCounts['lead_calls'] = DB::table('lead_calls')->whereIn('lead_id', $leadIds)->delete();
                $deletedCounts['lead_status_history'] = DB::table('lead_status_history')->whereIn('lead_id', $leadIds)->delete();

                // Optional dependent tables if present in schema
                if ($this->tableHasColumn('lead_metadata', 'lead_id')) {
                    $deletedCounts['lead_metadata'] = DB::table('lead_metadata')->whereIn('lead_id', $leadIds)->delete();
                }
                if ($this->tableHasColumn('lead_communications', 'lead_id')) {
                    $deletedCounts['lead_communications'] = DB::table('lead_communications')->whereIn('lead_id', $leadIds)->delete();
                }
                if ($this->tableHasColumn('bookings', 'lead_id')) {
                    $deletedCounts['bookings'] = DB::table('bookings')->whereIn('lead_id', $leadIds)->delete();
                }
                if ($this->tableHasColumn('meetings', 'lead_id')) {
                    $deletedCounts['meetings'] = DB::table('meetings')->whereIn('lead_id', $leadIds)->delete();
                }
                if ($this->tableHasColumn('site_visits', 'lead_id')) {
                    $deletedCounts['site_visits'] = DB::table('site_visits')->whereIn('lead_id', $leadIds)->delete();
                }
                if ($this->tableHasColumn('proposals', 'lead_id')) {
                    $deletedCounts['proposals'] = DB::table('proposals')->whereIn('lead_id', $leadIds)->delete();
                }
                if ($this->tableHasColumn('negotiations', 'lead_id')) {
                    $deletedCounts['negotiations'] = DB::table('negotiations')->whereIn('lead_id', $leadIds)->delete();
                }
                if ($this->tableHasColumn('internal_lead_processing', 'lead_id')) {
                    $deletedCounts['internal_lead_processing'] = DB::table('internal_lead_processing')->whereIn('lead_id', $leadIds)->delete();
                }
                if ($this->tableHasColumn('lead_delivery_items', 'lead_id')) {
                    $deletedCounts['lead_delivery_items'] = DB::table('lead_delivery_items')->whereIn('lead_id', $leadIds)->delete();
                }
                if ($this->tableHasColumn('lead_messages', 'lead_id')) {
                    $deletedCounts['lead_messages'] = DB::table('lead_messages')->whereIn('lead_id', $leadIds)->delete();
                }
                if ($this->tableHasColumn('lead_emails', 'lead_id')) {
                    $deletedCounts['lead_emails'] = DB::table('lead_emails')->whereIn('lead_id', $leadIds)->delete();
                }
            }

            // -------------------------------------------------------------
            // Stage 2: Credit Transactions & Wallets
            // -------------------------------------------------------------
            $deletedCounts['credit_transactions'] = DB::table('credit_transactions')->where(function ($q) use ($leadIds, $userIds) {
                if (!empty($leadIds)) {
                    $q->whereIn('lead_id', $leadIds)
                      ->orWhereIn('delivery_charge_lead_id', $leadIds);
                }
                if (!empty($userIds)) {
                    $q->orWhereIn('client_id', $userIds);
                }
            })->delete();

            if (!empty($userIds)) {
                if ($this->tableHasColumn('client_wallets', 'client_id')) {
                    $deletedCounts['client_wallets'] = DB::table('client_wallets')->whereIn('client_id', $userIds)->delete();
                }
                if ($this->tableHasColumn('recharge_requests', 'client_id')) {
                    $deletedCounts['recharge_requests'] = DB::table('recharge_requests')->whereIn('client_id', $userIds)->delete();
                }
                if ($this->tableHasColumn('credit_disputes', 'opened_by')) {
                    $deletedCounts['credit_disputes'] = DB::table('credit_disputes')->whereIn('opened_by', $userIds)->delete();
                }
                if ($this->tableHasColumn('invoices', 'client_id')) {
                    $deletedCounts['invoices'] = DB::table('invoices')->whereIn('client_id', $userIds)->delete();
                }
            }

            // -------------------------------------------------------------
            // Stage 3: Delete Leads
            // -------------------------------------------------------------
            if (!empty($leadIds)) {
                $deletedCounts['leads'] = DB::table('leads')->whereIn('id', $leadIds)->delete();
            }

            // -------------------------------------------------------------
            // Stage 4: Campaign Dependencies & Campaigns
            // -------------------------------------------------------------
            if (!empty($campaignIds)) {
                if ($this->tableHasColumn('campaign_sources', 'campaign_id')) {
                    $deletedCounts['campaign_sources'] = DB::table('campaign_sources')->whereIn('campaign_id', $campaignIds)->delete();
                }
                if ($this->tableHasColumn('lead_delivery_plans', 'campaign_id')) {
                    $deletedCounts['lead_delivery_plans'] = DB::table('lead_delivery_plans')->whereIn('campaign_id', $campaignIds)->delete();
                }
                if ($this->tableHasColumn('lead_form_mappings', 'campaign_id')) {
                    $deletedCounts['lead_form_mappings'] = DB::table('lead_form_mappings')->whereIn('campaign_id', $campaignIds)->delete();
                }
                if ($this->tableHasColumn('meta_ad_records', 'crm_campaign_id')) {
                    $deletedCounts['meta_ad_records_crm_links'] = DB::table('meta_ad_records')->whereIn('crm_campaign_id', $campaignIds)->update(['crm_campaign_id' => null]);
                }
                if ($this->tableHasColumn('upload_batches', 'campaign_id')) {
                    $deletedCounts['upload_batches'] = DB::table('upload_batches')->whereIn('campaign_id', $campaignIds)->delete();
                }

                $deletedCounts['campaigns'] = DB::table('campaigns')->whereIn('id', $campaignIds)->delete();
            }

            // -------------------------------------------------------------
            // Stage 5: Project Dependencies & Projects
            // -------------------------------------------------------------
            if (!empty($projectIds)) {
                if ($this->tableHasColumn('channel_partners', 'project_id')) {
                    $deletedCounts['channel_partners'] = DB::table('channel_partners')->whereIn('project_id', $projectIds)->delete();
                }
                if ($this->tableHasColumn('distribution_rules', 'project_id')) {
                    $deletedCounts['distribution_rules'] = DB::table('distribution_rules')->whereIn('project_id', $projectIds)->delete();
                }
                if ($this->tableHasColumn('project_units', 'project_id')) {
                    $deletedCounts['project_units'] = DB::table('project_units')->whereIn('project_id', $projectIds)->delete();
                }
                if ($this->tableHasColumn('lead_form_mappings', 'project_id')) {
                    $deletedCounts['lead_form_mappings_proj'] = DB::table('lead_form_mappings')->whereIn('project_id', $projectIds)->delete();
                }

                $deletedCounts['projects'] = DB::table('projects')->whereIn('id', $projectIds)->delete();
            }

            // -------------------------------------------------------------
            // Stage 6: Sales Teams & Members
            // -------------------------------------------------------------
            if (!empty($userIds) || !empty($salesTeamIds)) {
                $deletedCounts['sales_team_members'] = DB::table('sales_team_members')->where(function ($q) use ($userIds, $salesTeamIds) {
                    if (!empty($userIds)) $q->whereIn('user_id', $userIds);
                    if (!empty($salesTeamIds)) $q->orWhereIn('sales_team_id', $salesTeamIds);
                })->delete();
            }

            if (!empty($salesTeamIds)) {
                $deletedCounts['sales_teams'] = DB::table('sales_teams')->whereIn('id', $salesTeamIds)->delete();
            }

            // -------------------------------------------------------------
            // Stage 7: Notifications, General Settings, Push
            // -------------------------------------------------------------
            if (!empty($userIds)) {
                $hasOrgCol = $this->tableHasColumn('notifications', 'organization_id');
                $deletedCounts['notifications'] = DB::table('notifications')->where(function ($q) use ($userIds, $hasOrgCol) {
                    if ($hasOrgCol) {
                        $q->where('organization_id', self::TARGET_ORG_ID);
                    }
                    if (!empty($userIds)) {
                        $userFilter = function ($sq) use ($userIds) {
                            $sq->where('notifiable_type', User::class)->whereIn('notifiable_id', $userIds);
                        };
                        if ($hasOrgCol) {
                            $q->orWhere($userFilter);
                        } else {
                            $userFilter($q);
                        }
                    }
                })->delete();

                if ($this->tableHasColumn('general_settings', 'user_id')) {
                    $deletedCounts['general_settings'] = DB::table('general_settings')->whereIn('user_id', $userIds)->delete();
                }
                if ($this->tableHasColumn('browser_push_deliveries', 'user_id')) {
                    $deletedCounts['browser_push_deliveries'] = DB::table('browser_push_deliveries')->whereIn('user_id', $userIds)->delete();
                }
                if ($this->tableHasColumn('browser_push_subscriptions', 'user_id')) {
                    $deletedCounts['browser_push_subscriptions'] = DB::table('browser_push_subscriptions')->whereIn('user_id', $userIds)->delete();
                }
            }

            // -------------------------------------------------------------
            // Stage 8: Audit Logs created by NRR dummy seed operations
            // -------------------------------------------------------------
            // Note: SeederRunService enforces immutable audit logs during tracked seeder runs
            // (e.g. when executed via /admin/git-sync UI). If running inside a tracked run,
            // audit logs must be preserved to maintain compliance and avoid transaction rollback.
            $isRecording = app()->bound(\App\Services\SeederRunService::class)
                && app(\App\Services\SeederRunService::class)->isRecording();

            if ($isRecording) {
                $this->command?->info("  - audit_logs : Preserved (audit trail integrity enforced by seeder tracking system)");
            } else {
                try {
                    $deletedCounts['audit_logs'] = DB::table('audit_logs')->where(function ($q) use ($userIds) {
                        if (!empty($userIds)) {
                            $q->whereIn('user_id', $userIds)
                              ->orWhere(function ($sq) use ($userIds) {
                                  $sq->where('subject_type', User::class)->whereIn('subject_id', $userIds);
                              });
                        }
                        $q->orWhere(function ($sq) {
                            $sq->where('subject_type', Organization::class)
                               ->where('subject_id', self::TARGET_ORG_ID)
                               ->whereIn('action', ['credit.recharge', 'project.created', 'team.members_assigned', 'replacement.approved']);
                        });
                    })->delete();
                } catch (\Throwable $e) {
                    $this->command?->warn("  - audit_logs : Skipped deletion (" . $e->getMessage() . ")");
                }
            }

            // -------------------------------------------------------------
            // Stage 9: Spatie Roles & Permissions for NRR Dummy Users
            // -------------------------------------------------------------
            if (!empty($userIds)) {
                $deletedCounts['model_has_roles'] = DB::table('model_has_roles')
                    ->where('model_type', User::class)
                    ->whereIn('model_id', $userIds)
                    ->delete();

                if (\Illuminate\Support\Facades\Schema::hasTable('model_has_permissions')) {
                    $deletedCounts['model_has_permissions'] = DB::table('model_has_permissions')
                        ->where('model_type', User::class)
                        ->whereIn('model_id', $userIds)
                        ->delete();
                }

                // ---------------------------------------------------------
                // Stage 10: Delete NRR Dummy Users
                // ---------------------------------------------------------
                $deletedCounts['users'] = DB::table('users')->whereIn('id', $userIds)->delete();
            }
        });

        // 5. Verification after deletion
        $verifiedOrg = Organization::find(self::TARGET_ORG_ID);
        if (!$verifiedOrg) {
            throw new \RuntimeException("CRITICAL ERROR: Organization ID " . self::TARGET_ORG_ID . " was deleted! Transaction rolled back.");
        }

        // 6. Display Completed Results
        $this->command?->info("\n================================================================================");
        $this->command?->info(" [NRR DUMMY DATA CLEANUP COMPLETED SUCCESSFULLY]");
        $this->command?->info("================================================================================");

        $totalDeleted = 0;
        foreach ($deletedCounts as $table => $count) {
            if ($count > 0) {
                $this->command?->info(sprintf("  - %-28s : %d records deleted", $table, $count));
                $totalDeleted += $count;
            }
        }

        $this->command?->info("--------------------------------------------------------------------------------");
        $this->command?->info(sprintf("  TOTAL RECORDS PERMANENTLY REMOVED : %d", $totalDeleted));
        $this->command?->info("--------------------------------------------------------------------------------");
        $this->command?->info("  MASTER ORGANIZATION PRESERVED     : ID {$verifiedOrg->id} ({$verifiedOrg->name})");
        $this->command?->info("================================================================================\n");
    }

    /**
     * Resolves the exact IDs of all dummy records created for Organization 34.
     */
    public function resolveNrrDummyScope(): array
    {
        $orgId = self::TARGET_ORG_ID;

        // 1. Identify Dummy Users (5 users)
        $userIds = User::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->where(function ($q) {
                $q->whereIn('email', self::DUMMY_EMAILS)
                  ->orWhereIn('id', [83, 87, 88, 89, 90]);
            })
            ->pluck('id')
            ->toArray();

        // 2. Identify Dummy Projects (3 projects)
        $projectIds = Project::withoutGlobalScopes()
            ->where(function ($q) use ($userIds) {
                if (!empty($userIds)) {
                    $q->whereIn('client_id', $userIds);
                }
                $q->orWhereIn('id', [225, 226, 227])
                  ->orWhereIn('name', [
                      'NRR Green Valley Phase 1',
                      'NRR Skyline Towers',
                      'NRR Silicon Hub Commercial'
                  ]);
            })
            ->pluck('id')
            ->toArray();

        // 3. Identify Dummy Campaigns (7 campaigns)
        $campaignIds = Campaign::withoutGlobalScopes()
            ->where(function ($q) use ($projectIds) {
                if (!empty($projectIds)) {
                    $q->whereIn('project_id', $projectIds);
                }
                $q->orWhereIn('id', [424, 425, 426, 427, 428, 429, 430])
                  ->orWhere('name', 'like', 'NRR_%');
            })
            ->pluck('id')
            ->toArray();

        // 4. Identify Dummy Leads (20 leads)
        $leadIds = Lead::withoutGlobalScopes()
            ->where(function ($q) use ($userIds, $campaignIds) {
                if (!empty($userIds)) {
                    $q->whereIn('client_id', $userIds);
                }
                if (!empty($campaignIds)) {
                    $q->orWhereIn('campaign_id', $campaignIds);
                }
                $q->orWhere('lead_code', 'like', 'LP-2026-000034%');
            })
            ->pluck('id')
            ->toArray();

        // 5. Identify Dummy Sales Teams (1 team)
        $salesTeamIds = SalesTeam::withoutGlobalScopes()
            ->where('ownable_type', Organization::class)
            ->where('ownable_id', $orgId)
            ->pluck('id')
            ->toArray();

        return [
            'users' => $userIds,
            'projects' => $projectIds,
            'campaigns' => $campaignIds,
            'leads' => $leadIds,
            'sales_teams' => $salesTeamIds,
        ];
    }

    /**
     * Generates a dry-run report table.
     */
    public function generateReport(array $scope, bool $print = true): array
    {
        $userIds = $scope['users'];
        $projectIds = $scope['projects'];
        $campaignIds = $scope['campaigns'];
        $leadIds = $scope['leads'];
        $salesTeamIds = $scope['sales_teams'];

        $hasOrgCol = $this->tableHasColumn('notifications', 'organization_id');
        $isRecording = app()->bound(\App\Services\SeederRunService::class)
            && app(\App\Services\SeederRunService::class)->isRecording();

        $report = [
            'lead_assignments' => !empty($leadIds) ? DB::table('lead_assignments')->whereIn('lead_id', $leadIds)->count() : 0,
            'lead_replacements' => !empty($leadIds) ? DB::table('lead_replacements')->whereIn('lead_id', $leadIds)->count() : 0,
            'followups' => !empty($leadIds) ? DB::table('followups')->whereIn('lead_id', $leadIds)->count() : 0,
            'lead_calls' => !empty($leadIds) ? DB::table('lead_calls')->whereIn('lead_id', $leadIds)->count() : 0,
            'lead_status_history' => !empty($leadIds) ? DB::table('lead_status_history')->whereIn('lead_id', $leadIds)->count() : 0,
            'credit_transactions' => DB::table('credit_transactions')->where(function ($q) use ($leadIds, $userIds) {
                if (!empty($leadIds)) {
                    $q->whereIn('lead_id', $leadIds)->orWhereIn('delivery_charge_lead_id', $leadIds);
                }
                if (!empty($userIds)) {
                    $q->orWhereIn('client_id', $userIds);
                }
            })->count(),
            'client_wallets' => (!empty($userIds) && $this->tableHasColumn('client_wallets', 'client_id')) ? DB::table('client_wallets')->whereIn('client_id', $userIds)->count() : 0,
            'leads' => count($leadIds),
            'campaigns' => count($campaignIds),
            'projects' => count($projectIds),
            'sales_team_members' => DB::table('sales_team_members')->where(function ($q) use ($userIds, $salesTeamIds) {
                if (!empty($userIds)) $q->whereIn('user_id', $userIds);
                if (!empty($salesTeamIds)) $q->orWhereIn('sales_team_id', $salesTeamIds);
            })->count(),
            'sales_teams' => count($salesTeamIds),
            'notifications' => DB::table('notifications')->where(function ($q) use ($userIds, $hasOrgCol) {
                if ($hasOrgCol) {
                    $q->where('organization_id', self::TARGET_ORG_ID);
                }
                if (!empty($userIds)) {
                    $userFilter = function ($sq) use ($userIds) {
                        $sq->where('notifiable_type', User::class)->whereIn('notifiable_id', $userIds);
                    };
                    if ($hasOrgCol) {
                        $q->orWhere($userFilter);
                    } else {
                        $userFilter($q);
                    }
                }
            })->count(),
            'general_settings' => (!empty($userIds) && $this->tableHasColumn('general_settings', 'user_id')) ? DB::table('general_settings')->whereIn('user_id', $userIds)->count() : 0,
            'model_has_roles' => !empty($userIds) ? DB::table('model_has_roles')->where('model_type', User::class)->whereIn('model_id', $userIds)->count() : 0,
            'audit_logs' => (!$isRecording && \Illuminate\Support\Facades\Schema::hasTable('audit_logs')) ? DB::table('audit_logs')->where(function ($q) use ($userIds) {
                if (!empty($userIds)) {
                    $q->whereIn('user_id', $userIds)
                      ->orWhere(function ($sq) use ($userIds) {
                          $sq->where('subject_type', User::class)->whereIn('subject_id', $userIds);
                      });
                }
                $q->orWhere(function ($sq) {
                    $sq->where('subject_type', Organization::class)
                       ->where('subject_id', self::TARGET_ORG_ID)
                       ->whereIn('action', ['credit.recharge', 'project.created', 'team.members_assigned', 'replacement.approved']);
                });
            })->count() : 0,
            'users' => count($userIds),
        ];

        if ($print && $this->command) {
            $this->command->info("\n--- PRE-CLEANUP VERIFICATION REPORT ---");
            $total = 0;
            foreach ($report as $table => $count) {
                $this->command->info(sprintf("  - %-26s : %d records to delete", $table, $count));
                $total += $count;
            }
            $this->command->info("--------------------------------------------------------------------------------");
            $this->command->info(sprintf("  TOTAL DUMMY RECORDS IDENTIFIED : %d", $total));
            $this->command->info("  ORGANIZATION RECORD TO KEEP    : ID 34 (NRR) [PRESERVED]");
            $this->command->info("--------------------------------------------------------------------------------");
        }

        return $report;
    }

    /**
     * Check if a table exists and contains a column.
     */
    private function tableHasColumn(string $table, string $column): bool
    {
        return \Illuminate\Support\Facades\Schema::hasTable($table)
            && \Illuminate\Support\Facades\Schema::hasColumn($table, $column);
    }
}
