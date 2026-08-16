<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Organization;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $platformOrg = Organization::where('type', 'platform')->first();
        $builderOrgs = Organization::where('type', 'builder')->get();
        $partnerOrgs = Organization::where('type', 'channel_partner')->get();

        // 1 Super Admin
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@leadpanther.com'],
            [
                'organization_id' => $platformOrg->id ?? null,
                'name' => 'Super Admin',
                'password' => bcrypt('password'),
                'phone' => '+919800000001',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        $superAdmin->assignRole('super-admin');

        // 2 Builder Users
        foreach ($builderOrgs as $index => $org) {
            $user = User::firstOrCreate(
                ['email' => 'builder' . ($index + 1) . '@venture.com'],
                [
                    'organization_id' => $org->id,
                    'name' => 'Builder Manager ' . ($index + 1),
                    'password' => bcrypt('password'),
                    'phone' => '+91980000000' . ($index + 2),
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]
            );
            $user->assignRole('builder');
        }

        // 6 Channel Partner Users
        foreach ($partnerOrgs as $pIndex => $pOrg) {
            for ($i = 1; $i <= 2; $i++) {
                $pUser = User::firstOrCreate(
                    ['email' => 'partner_' . $pOrg->id . '_' . $i . '@cp.com'],
                    [
                        'organization_id' => $pOrg->id,
                        'name' => $pOrg->name . ' Partner ' . $i,
                        'password' => bcrypt('password'),
                        'phone' => '+91980000010' . ($pIndex * 2 + $i),
                        'status' => 'active',
                        'email_verified_at' => now(),
                    ]
                );
                $pUser->assignRole('channel-partner');
            }
        }

        // 15 Sales Executives
        for ($s = 1; $s <= 15; $s++) {
            $salesUser = User::firstOrCreate(
                ['email' => 'sales' . $s . '@leadpanther.com'],
                [
                    'organization_id' => $builderOrgs->random()->id,
                    'name' => 'Sales Exec ' . $s,
                    'password' => bcrypt('password'),
                    'phone' => '+91980000020' . $s,
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]
            );
            $salesUser->assignRole('sales-executive');
        }

        // 3 Account Managers
        for ($am = 1; $am <= 3; $am++) {
            $amUser = User::firstOrCreate(
                ['email' => 'accountmgr' . $am . '@leadpanther.com'],
                [
                    'organization_id' => $platformOrg->id ?? null,
                    'name' => 'Account Manager ' . $am,
                    'password' => bcrypt('password'),
                    'phone' => '+91980000030' . $am,
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]
            );
            $amUser->assignRole('account-manager');
        }

        // 4 Client Users
        for ($c = 1; $c <= 4; $c++) {
            $clientUser = User::firstOrCreate(
                ['email' => 'client' . $c . '@client.com'],
                [
                    'organization_id' => $builderOrgs->random()->id,
                    'name' => 'Client User ' . $c,
                    'password' => bcrypt('password'),
                    'phone' => '+91980000040' . $c,
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]
            );
            $clientUser->assignRole('client');
        }
    }
}
