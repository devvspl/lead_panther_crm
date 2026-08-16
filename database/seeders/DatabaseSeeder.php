<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database wrapped in a single DB transaction.
     */
    public function run(): void
    {
        DB::transaction(function () {
            $this->call([
                RoleAndPermissionSeeder::class,
                OrganizationSeeder::class,
                UserSeeder::class,
                AccountAccessFixSeeder::class,
                ClientProjectSeeder::class,
                LeadSourceAndCampaignSeeder::class,
                ChannelPartnerHierarchySeeder::class,
                DistributionRuleSeeder::class,
                CreditPackageSeeder::class,
                LeadSeeder::class,
                CreditTransactionSeeder::class,
                CommunicationSeeder::class,
                ActivitySeeder::class,
                ReplacementSeeder::class,
                AuditLogSeeder::class,
            ]);
        });
    }
}
