<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Organization;
use App\Models\User;
use App\Models\Client;
use App\Models\Project;
use App\Models\LeadSource;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\CreditPackage;
use App\Models\CreditWallet;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LeadPantherDemoSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Organization
        $org = Organization::create([
            'name' => 'Venture Realty Developers',
            'type' => 'builder',
            'status' => 'active',
        ]);

        // 2. Create Super Admin User
        $admin = User::create([
            'organization_id' => $org->id,
            'name' => 'Admin User',
            'email' => 'admin@leadpanther.com',
            'password' => Hash::make('password'),
            'phone' => '+919876543210',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('Super Admin');

        // 3. Create Client
        $client = Client::create([
            'organization_id' => $org->id,
            'name' => 'Apex Real Estate Ventures',
            'billing_email' => 'billing@apexrealty.com',
            'status' => 'active',
        ]);

        // 4. Create Client Wallet
        CreditWallet::create([
            'client_id' => $client->id,
            'balance' => 2500.00,
        ]);

        // 5. Create Credit Packages
        CreditPackage::create(['name' => 'Starter Pack', 'credit_count' => 100, 'price' => 5000.00, 'validity_days' => 30]);
        CreditPackage::create(['name' => 'Growth Pack', 'credit_count' => 500, 'price' => 22000.00, 'validity_days' => 60]);
        CreditPackage::create(['name' => 'Enterprise Pack', 'credit_count' => 2000, 'price' => 80000.00, 'validity_days' => 90]);

        // 6. Create Projects
        $project1 = Project::create([
            'client_id' => $client->id,
            'name' => 'Orchid Residency Phase 1',
            'location' => 'Whitefield, Bengaluru',
            'property_type' => 'Residential Apartments',
            'price_range_min' => 7500000.00,
            'price_range_max' => 15000000.00,
            'status' => 'active',
        ]);

        // 7. Create Lead Sources
        $metaSource = LeadSource::create(['name' => 'meta', 'is_active' => true]);
        $googleSource = LeadSource::create(['name' => 'google', 'is_active' => true]);
        $portalSource = LeadSource::create(['name' => 'portal', 'is_active' => true]);

        // 8. Create Campaign
        $campaign = Campaign::create([
            'project_id' => $project1->id,
            'lead_source_id' => $metaSource->id,
            'name' => 'Orchid_Residency_Meta_Q3',
            'utm_campaign' => 'meta_q3_leads',
            'budget' => 150000.00,
            'status' => 'active',
            'starts_at' => now()->subDays(15),
            'ends_at' => now()->addDays(45),
        ]);

        // 9. Sample Leads
        Lead::create([
            'lead_code' => 'LP-' . date('Y') . '-00001001',
            'client_id' => $client->id,
            'project_id' => $project1->id,
            'campaign_id' => $campaign->id,
            'lead_source_id' => $metaSource->id,
            'name' => 'Rahul Sharma',
            'mobile' => '+919811223344',
            'email' => 'rahul.sharma@example.com',
            'city' => 'Bengaluru',
            'location' => 'Whitefield',
            'budget' => 8500000.00,
            'property_type' => '3 BHK',
            'status' => 'new',
            'current_stage' => 'new',
            'lead_score' => 85,
            'assigned_to' => $admin->id,
        ]);
    }
}
