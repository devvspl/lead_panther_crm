<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Organization;
use App\Models\User;
use App\Models\Project;
use App\Models\Campaign;
use App\Models\LeadSource;
use App\Models\Lead;
use App\Models\CreditWallet;
use App\Models\CreditTransaction;
use App\Models\Followup;
use App\Models\LeadCall;
use App\Models\LeadAssignment;
use App\Models\LeadReplacement;
use App\Models\SalesTeam;
use App\Models\SalesTeamMember;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Carbon\Carbon;

class Organization34SampleSeeder extends Seeder
{
    /**
     * Seed realistic sample data specifically for Organization ID 34 (NRR).
     * Populates:
     * - Organization Profile & Settings
     * - Primary Builder User (NRR) & 4 Sales Executives
     * - Sales Team structure
     * - 3 Commercial & Residential Projects
     * - Active Ingestion Campaigns across Meta, Google, Portals & Manual
     * - Credit Wallet & Transaction Ledger (Opening, Recharges, Deductions & Refunds)
     * - 20 Diverse Sample Leads with complete lifecycle stages (New, Assigned, Contacted, Site Visit, Won, Replacement)
     * - Active Follow-ups (Due Today, Overdue, Upcoming, Completed)
     * - Call logs, Assignment history & Replacement approval
     */
    public function run(): void
    {
        $this->command?->info("Seeding realistic sample data for Organization ID 34 (NRR)...");

        // ─────────────────────────────────────────────────────────────
        // 1. Resolve or Create Roles
        // ─────────────────────────────────────────────────────────────
        $builderRole = Role::firstOrCreate(['name' => 'Builder/Developer', 'guard_name' => 'web']);
        $salesRole = Role::firstOrCreate(['name' => 'Sales Executive', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);

        // ─────────────────────────────────────────────────────────────
        // 2. Resolve or Create Organization 34 (NRR)
        // ─────────────────────────────────────────────────────────────
        $org = Organization::find(34);
        if (!$org) {
            $org = Organization::create([
                'id' => 34,
                'name' => 'NRR',
                'status' => 'active',
                'auto_assign_enabled' => 1,
                'distribution_method' => 'round_robin',
                'min_credit_threshold' => 50.00,
                'lead_cost' => null, // Inherits global default or project overrides
            ]);
        } else {
            $org->update([
                'name' => 'NRR',
                'status' => 'active',
                'auto_assign_enabled' => 1,
                'distribution_method' => 'round_robin',
                'min_credit_threshold' => 50.00,
            ]);
        }

        // ─────────────────────────────────────────────────────────────
        // 3. Resolve or Create Primary Builder User (ID 83 or nrr@leadpanther.crm)
        // ─────────────────────────────────────────────────────────────
        $builderUser = User::where('email', 'nrr@leadpanther.crm')->first();
        if (!$builderUser) {
            $builderUser = User::find(83);
        }

        if (!$builderUser) {
            $builderUser = User::create([
                'id' => 83,
                'organization_id' => 34,
                'name' => 'NRR',
                'email' => 'nrr@leadpanther.crm',
                'phone' => '+919876543234',
                'password' => Hash::make('password'),
                'status' => 'active',
                'email_verified_at' => now(),
            ]);
        } else {
            $builderUser->update([
                'organization_id' => 34,
                'name' => 'NRR',
                'status' => 'active',
            ]);
        }

        try {
            if (!$builderUser->hasRole('Builder/Developer')) {
                $builderUser->assignRole($builderRole);
            }
        } catch (\Throwable $e) {}

        // ─────────────────────────────────────────────────────────────
        // 4. Create 4 Dedicated Sales Executives for Organization 34
        // ─────────────────────────────────────────────────────────────
        $salesAgentsData = [
            [
                'name' => 'Aman Sharma',
                'email' => 'aman.sharma@nrrbuildcon.com',
                'phone' => '+919810123401',
            ],
            [
                'name' => 'Priya Patel',
                'email' => 'priya.patel@nrrbuildcon.com',
                'phone' => '+919810123402',
            ],
            [
                'name' => 'Vikram Malhotra',
                'email' => 'vikram.malhotra@nrrbuildcon.com',
                'phone' => '+919810123403',
            ],
            [
                'name' => 'Sneha Reddy',
                'email' => 'sneha.reddy@nrrbuildcon.com',
                'phone' => '+919810123404',
            ],
        ];

        $teamAgents = [];
        foreach ($salesAgentsData as $agentInfo) {
            $agent = User::firstOrCreate(
                ['email' => $agentInfo['email']],
                [
                    'organization_id' => 34,
                    'name' => $agentInfo['name'],
                    'phone' => $agentInfo['phone'],
                    'password' => Hash::make('password'),
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]
            );

            $agent->update(['organization_id' => 34, 'status' => 'active']);

            try {
                if (!$agent->hasRole('Sales Executive')) {
                    $agent->assignRole($salesRole);
                }
            } catch (\Throwable $e) {}

            $teamAgents[] = $agent;
        }

        // ─────────────────────────────────────────────────────────────
        // 5. Create Sales Team Structure for NRR
        // ─────────────────────────────────────────────────────────────
        $salesTeam = SalesTeam::firstOrCreate(
            ['ownable_type' => Organization::class, 'ownable_id' => 34],
            ['name' => 'NRR Direct Sales Core', 'lead_cost' => null]
        );

        foreach ($teamAgents as $agent) {
            SalesTeamMember::firstOrCreate(
                ['sales_team_id' => $salesTeam->id, 'user_id' => $agent->id],
                [
                    'role' => 'sales_executive',
                    'is_active' => true,
                    'max_capacity' => 30,
                ]
            );
        }

        // ─────────────────────────────────────────────────────────────
        // 6. Create 3 Signature Projects for NRR
        // ─────────────────────────────────────────────────────────────
        $project1 = Project::firstOrCreate(
            ['client_id' => $builderUser->id, 'name' => 'NRR Green Valley Phase 1'],
            [
                'location' => 'Sector 150, Noida Expressway',
                'property_type' => '3 & 4 BHK Luxury Apartments',
                'price_range_min' => 8500000.00,
                'price_range_max' => 17500000.00,
                'lead_cost' => 15.00,
                'status' => 'active',
            ]
        );

        $project2 = Project::firstOrCreate(
            ['client_id' => $builderUser->id, 'name' => 'NRR Skyline Towers'],
            [
                'location' => 'Golf Course Extension Road, Gurgaon',
                'property_type' => 'High-Rise Premium Residences',
                'price_range_min' => 12000000.00,
                'price_range_max' => 28000000.00,
                'lead_cost' => 20.00,
                'status' => 'active',
            ]
        );

        $project3 = Project::firstOrCreate(
            ['client_id' => $builderUser->id, 'name' => 'NRR Silicon Hub Commercial'],
            [
                'location' => 'Whitefield, Bengaluru',
                'property_type' => 'Grade-A Office & Retail Spaces',
                'price_range_min' => 4500000.00,
                'price_range_max' => 9500000.00,
                'lead_cost' => 12.00,
                'status' => 'active',
            ]
        );

        // ─────────────────────────────────────────────────────────────
        // 7. Ensure Lead Sources and Ingestion Campaigns Exist
        // ─────────────────────────────────────────────────────────────
        $metaSource = LeadSource::firstOrCreate(['name' => 'meta'], ['is_active' => true]);
        $googleSource = LeadSource::firstOrCreate(['name' => 'google'], ['is_active' => true]);
        $portalSource = LeadSource::firstOrCreate(['name' => 'portal'], ['is_active' => true]);
        $manualSource = LeadSource::firstOrCreate(['name' => 'manual'], ['is_active' => true]);

        $campaign1 = Campaign::firstOrCreate(
            ['project_id' => $project1->id, 'name' => 'NRR_GreenValley_Meta_HighIntent'],
            [
                'lead_source_id' => $metaSource->id,
                'utm_campaign' => 'fb_noida_sec150_luxury',
                'budget' => 150000.00,
                'status' => 'active',
                'starts_at' => now()->subDays(30),
            ]
        );

        $campaign2 = Campaign::firstOrCreate(
            ['project_id' => $project2->id, 'name' => 'NRR_Skyline_Google_Search_NRI'],
            [
                'lead_source_id' => $googleSource->id,
                'utm_campaign' => 'ggl_gurgaon_nri_search',
                'budget' => 200000.00,
                'status' => 'active',
                'starts_at' => now()->subDays(25),
            ]
        );

        $campaign3 = Campaign::firstOrCreate(
            ['project_id' => $project3->id, 'name' => 'NRR_SiliconHub_HousingPortal'],
            [
                'lead_source_id' => $portalSource->id,
                'utm_campaign' => 'magicbricks_99acres_blr',
                'budget' => 80000.00,
                'status' => 'active',
                'starts_at' => now()->subDays(20),
            ]
        );

        $campaign4 = Campaign::firstOrCreate(
            ['project_id' => $project1->id, 'name' => 'NRR_Direct_Walkin_Referrals'],
            [
                'lead_source_id' => $manualSource->id,
                'utm_campaign' => 'walkin_channel_partner',
                'budget' => 50000.00,
                'status' => 'active',
                'starts_at' => now()->subDays(30),
            ]
        );

        // ─────────────────────────────────────────────────────────────
        // 8. Seed Credit Wallet & Transaction Ledger
        // ─────────────────────────────────────────────────────────────
        $wallet = CreditWallet::firstOrCreate(
            ['client_id' => $builderUser->id],
            ['balance' => 0.00, 'opening_balance' => 0.00]
        );

        // Clear existing transactions for fresh idempotence
        CreditTransaction::where('client_id', $builderUser->id)->delete();

        // Transaction 1: Opening Credit
        CreditTransaction::create([
            'client_id' => $builderUser->id,
            'lead_id' => null,
            'package_id' => null,
            'credit_before' => 0.00,
            'credit_used' => 500.00,
            'credit_after' => 500.00,
            'transaction_type' => 'opening_credit',
            'description' => 'Initial opening balance allocation for NRR',
            'created_by' => 1,
            'created_at' => now()->subDays(20),
        ]);

        // Transaction 2: Recharge by Super Admin
        CreditTransaction::create([
            'client_id' => $builderUser->id,
            'lead_id' => null,
            'package_id' => null,
            'credit_before' => 500.00,
            'credit_used' => 1000.00,
            'credit_after' => 1500.00,
            'transaction_type' => 'recharge',
            'description' => 'Super Admin bank transfer recharge - Invoice #INV-2026-088',
            'created_by' => 1,
            'created_at' => now()->subDays(12),
        ]);

        // ─────────────────────────────────────────────────────────────
        // 9. Prepare 20 Realistic Sample Leads with Diverse Lifecycle Stages
        // ─────────────────────────────────────────────────────────────
        // Clean existing leads for NRR to avoid duplicates on re-seeding
        $existingLeadIds = Lead::withoutGlobalScopes()->where('client_id', $builderUser->id)->pluck('id');
        if ($existingLeadIds->isNotEmpty()) {
            Followup::whereIn('lead_id', $existingLeadIds)->delete();
            LeadCall::whereIn('lead_id', $existingLeadIds)->delete();
            LeadAssignment::whereIn('lead_id', $existingLeadIds)->delete();
            LeadReplacement::whereIn('lead_id', $existingLeadIds)->delete();
            Lead::withoutGlobalScopes()->whereIn('id', $existingLeadIds)->delete();
        }

        $leadsDefinitions = [
            // ───────────────── TODAY'S LEADS (4 leads) ─────────────────
            [
                'lead_code' => 'LP-2026-00003401',
                'name' => 'Rajesh Khanna',
                'mobile' => '+919811223401',
                'email' => 'rajesh.khanna@delhihousing.in',
                'city' => 'Noida',
                'location' => 'Sector 150',
                'budget' => 9500000.00,
                'property_type' => '3 BHK Luxury',
                'requirement' => 'Corner unit on higher floor with park facing balcony.',
                'status' => 'new',
                'current_stage' => 'new',
                'lead_score' => 88,
                'assigned_agent_idx' => null, // Unassigned
                'project' => $project1,
                'campaign' => $campaign1,
                'source' => $metaSource,
                'created_at' => now()->subHours(2),
                'first_response_at' => null,
                'cost' => 15.00,
            ],
            [
                'lead_code' => 'LP-2026-00003402',
                'name' => 'Dr. Pradeep Joshi',
                'mobile' => '+919811223402',
                'email' => 'dr.joshi@maxhealthcare.org',
                'city' => 'Noida',
                'location' => 'Greater Noida Express',
                'budget' => 16000000.00,
                'property_type' => '4 BHK Premium',
                'requirement' => 'Spacious 4 BHK with 2 covered car parking slots. Immediate decision.',
                'status' => 'assigned',
                'current_stage' => 'assigned',
                'lead_score' => 92,
                'assigned_agent_idx' => 0, // Aman Sharma
                'project' => $project1,
                'campaign' => $campaign1,
                'source' => $metaSource,
                'created_at' => now()->subHours(4),
                'first_response_at' => null,
                'cost' => 15.00,
            ],
            [
                'lead_code' => 'LP-2026-00003403',
                'name' => 'Sunita Mehra',
                'mobile' => '+919811223403',
                'email' => 'sunita.mehra@gmail.com',
                'city' => 'Gurgaon',
                'location' => 'Golf Course Extn',
                'budget' => 12500000.00,
                'property_type' => '3 BHK High-Rise',
                'requirement' => 'Looking for Vastu compliant north-east facing unit.',
                'status' => 'contacted',
                'current_stage' => 'contacted',
                'lead_score' => 78,
                'assigned_agent_idx' => 1, // Priya Patel
                'project' => $project2,
                'campaign' => $campaign2,
                'source' => $googleSource,
                'created_at' => now()->subHours(6),
                'first_response_at' => now()->subHours(5)->addMinutes(45),
                'cost' => 20.00,
            ],
            [
                'lead_code' => 'LP-2026-00003404',
                'name' => 'Abhinav Singhania',
                'mobile' => '+919811223404',
                'email' => 'abhinav@singhaniagroup.com',
                'city' => 'Gurgaon',
                'location' => 'Sector 65',
                'budget' => 27000000.00,
                'property_type' => 'Penthouse 4 BHK',
                'requirement' => 'High-end duplex penthouse with private terrace for self use.',
                'status' => 'contacted',
                'current_stage' => 'contacted',
                'lead_score' => 95,
                'assigned_agent_idx' => 2, // Vikram Malhotra
                'project' => $project2,
                'campaign' => $campaign2,
                'source' => $googleSource,
                'created_at' => now()->subHours(8),
                'first_response_at' => now()->subHours(7)->addMinutes(42),
                'cost' => 20.00,
            ],

            // ───────────── IN-PROGRESS & QUALIFIED (5 leads) ─────────────
            [
                'lead_code' => 'LP-2026-00003405',
                'name' => 'Ananya Roy',
                'mobile' => '+919811223405',
                'email' => 'ananya.roy@accenture.com',
                'city' => 'Noida',
                'location' => 'Expressway',
                'budget' => 8800000.00,
                'property_type' => '3 BHK Compact',
                'requirement' => 'Close to metro connectivity, bank loan pre-approved with HDFC.',
                'status' => 'in_progress',
                'current_stage' => 'connected',
                'lead_score' => 80,
                'assigned_agent_idx' => 3, // Sneha Reddy
                'project' => $project1,
                'campaign' => $campaign1,
                'source' => $metaSource,
                'created_at' => now()->subDays(2)->subHours(3),
                'first_response_at' => now()->subDays(2)->subHours(2)->addMinutes(40),
                'cost' => 15.00,
            ],
            [
                'lead_code' => 'LP-2026-00003406',
                'name' => 'Deepak Chawla',
                'mobile' => '+919811223406',
                'email' => 'deepak@chawlatextiles.com',
                'city' => 'Bengaluru',
                'location' => 'Whitefield Main Rd',
                'budget' => 6500000.00,
                'property_type' => 'Commercial Retail',
                'requirement' => 'Ground floor retail anchor shop for fashion boutique franchise.',
                'status' => 'in_progress',
                'current_stage' => 'interested',
                'lead_score' => 84,
                'assigned_agent_idx' => 0, // Aman Sharma
                'project' => $project3,
                'campaign' => $campaign3,
                'source' => $portalSource,
                'created_at' => now()->subDays(3)->subHours(5),
                'first_response_at' => now()->subDays(3)->subHours(4)->addMinutes(35),
                'cost' => 12.00,
            ],
            [
                'lead_code' => 'LP-2026-00003407',
                'name' => 'Kavita Sen',
                'mobile' => '+919811223407',
                'email' => 'kavita.sen@yahoo.co.in',
                'city' => 'Gurgaon',
                'location' => 'Golf Course Extn',
                'budget' => 14500000.00,
                'property_type' => '3 BHK Luxury',
                'requirement' => 'Prefers club facing middle tower. Looking for flexible payment plan.',
                'status' => 'in_progress',
                'current_stage' => 'qualified',
                'lead_score' => 86,
                'assigned_agent_idx' => 1, // Priya Patel
                'project' => $project2,
                'campaign' => $campaign2,
                'source' => $googleSource,
                'created_at' => now()->subDays(4)->subHours(2),
                'first_response_at' => now()->subDays(4)->subHours(1)->addMinutes(45),
                'cost' => 20.00,
            ],
            [
                'lead_code' => 'LP-2026-00003408',
                'name' => 'Manish Goel',
                'mobile' => '+919811223408',
                'email' => 'manish@goelelectronics.in',
                'city' => 'Noida',
                'location' => 'Sector 150',
                'budget' => 18000000.00,
                'property_type' => '4 BHK Duplex',
                'requirement' => 'Investment purpose. Expects rental yield of 4.5%+. Clean track builder needed.',
                'status' => 'in_progress',
                'current_stage' => 'qualified',
                'lead_score' => 89,
                'assigned_agent_idx' => 2, // Vikram Malhotra
                'project' => $project1,
                'campaign' => $campaign1,
                'source' => $metaSource,
                'created_at' => now()->subDays(5)->subHours(4),
                'first_response_at' => now()->subDays(5)->subHours(3)->addMinutes(40),
                'cost' => 15.00,
            ],
            [
                'lead_code' => 'LP-2026-00003409',
                'name' => 'Harish Batra',
                'mobile' => '+919811223409',
                'email' => 'harish.batra@batralaw.com',
                'city' => 'Bengaluru',
                'location' => 'Whitefield Tech Zone',
                'budget' => 7500000.00,
                'property_type' => 'Grade-A Office Suite',
                'requirement' => '1,200 sqft bare shell office for law chambers with 100% power backup.',
                'status' => 'in_progress',
                'current_stage' => 'qualified',
                'lead_score' => 87,
                'assigned_agent_idx' => 3, // Sneha Reddy
                'project' => $project3,
                'campaign' => $campaign3,
                'source' => $portalSource,
                'created_at' => now()->subDays(6)->subHours(6),
                'first_response_at' => now()->subDays(6)->subHours(5)->addMinutes(42),
                'cost' => 12.00,
            ],

            // ───────────── SITE VISITS & NEGOTIATIONS (5 leads) ─────────────
            [
                'lead_code' => 'LP-2026-00003410',
                'name' => 'Shalini Gupta',
                'mobile' => '+919811223410',
                'email' => 'shalini.gupta@delhincr.org',
                'city' => 'Noida',
                'location' => 'Sector 150',
                'budget' => 11000000.00,
                'property_type' => '3 BHK Park View',
                'requirement' => 'Site inspection scheduled today at 3:00 PM with spouse.',
                'status' => 'in_progress',
                'current_stage' => 'site_visit',
                'lead_score' => 94,
                'assigned_agent_idx' => 0, // Aman Sharma
                'project' => $project1,
                'campaign' => $campaign1,
                'source' => $metaSource,
                'created_at' => now()->subDays(7)->subHours(3),
                'first_response_at' => now()->subDays(7)->subHours(2)->addMinutes(45),
                'cost' => 15.00,
            ],
            [
                'lead_code' => 'LP-2026-00003411',
                'name' => 'Sanjay Agarwal',
                'mobile' => '+919811223411',
                'email' => 'sanjay@agarwalfinchem.com',
                'city' => 'Gurgaon',
                'location' => 'Golf Course Extn',
                'budget' => 21000000.00,
                'property_type' => '4 BHK Premium',
                'requirement' => 'Sample flat visited yesterday. Highly impressed with podium amenities.',
                'status' => 'in_progress',
                'current_stage' => 'site_visit',
                'lead_score' => 93,
                'assigned_agent_idx' => 1, // Priya Patel
                'project' => $project2,
                'campaign' => $campaign2,
                'source' => $googleSource,
                'created_at' => now()->subDays(8)->subHours(5),
                'first_response_at' => now()->subDays(8)->subHours(4)->addMinutes(40),
                'cost' => 20.00,
            ],
            [
                'lead_code' => 'LP-2026-00003412',
                'name' => 'Meenakshi Sundaram',
                'mobile' => '+919811223412',
                'email' => 'meenakshi.s@infosys.com',
                'city' => 'Bengaluru',
                'location' => 'Whitefield',
                'budget' => 8500000.00,
                'property_type' => 'Food Court Commercial',
                'requirement' => 'Site visit scheduled for coming Saturday. NRI buyer based in Singapore.',
                'status' => 'in_progress',
                'current_stage' => 'site_visit',
                'lead_score' => 85,
                'assigned_agent_idx' => 2, // Vikram Malhotra
                'project' => $project3,
                'campaign' => $campaign3,
                'source' => $portalSource,
                'created_at' => now()->subDays(9)->subHours(4),
                'first_response_at' => now()->subDays(9)->subHours(3)->addMinutes(45),
                'cost' => 12.00,
            ],
            [
                'lead_code' => 'LP-2026-00003413',
                'name' => 'Alok Nath Tandon',
                'mobile' => '+919811223413',
                'email' => 'alok.tandon@ntpc.co.in',
                'city' => 'Noida',
                'location' => 'Sector 150',
                'budget' => 9800000.00,
                'property_type' => '3 BHK Tower B',
                'requirement' => 'Price negotiation on floor rise charges and club membership waiver.',
                'status' => 'in_progress',
                'current_stage' => 'negotiation',
                'lead_score' => 91,
                'assigned_agent_idx' => 3, // Sneha Reddy
                'project' => $project1,
                'campaign' => $campaign1,
                'source' => $metaSource,
                'created_at' => now()->subDays(10)->subHours(2),
                'first_response_at' => now()->subDays(10)->subHours(1)->addMinutes(40),
                'cost' => 15.00,
            ],
            [
                'lead_code' => 'LP-2026-00003414',
                'name' => 'Gaurav Vashisht',
                'mobile' => '+919811223414',
                'email' => 'gaurav.v@microsoft.com',
                'city' => 'Gurgaon',
                'location' => 'Golf Course Extn',
                'budget' => 24000000.00,
                'property_type' => '4 BHK Luxury Tower A',
                'requirement' => 'Final token closing discussions with VP Sales. Unit 1402 shortlisted.',
                'status' => 'in_progress',
                'current_stage' => 'negotiation',
                'lead_score' => 96,
                'assigned_agent_idx' => 0, // Aman Sharma
                'project' => $project2,
                'campaign' => $campaign2,
                'source' => $googleSource,
                'created_at' => now()->subDays(11)->subHours(6),
                'first_response_at' => now()->subDays(11)->subHours(5)->addMinutes(45),
                'cost' => 20.00,
            ],

            // ───────────── CLOSED WON & BOOKINGS (2 leads) ─────────────
            [
                'lead_code' => 'LP-2026-00003415',
                'name' => 'Rohitashv Oberoi',
                'mobile' => '+919811223415',
                'email' => 'rohitashv@oberoicapital.com',
                'city' => 'Gurgaon',
                'location' => 'Golf Course Extn',
                'budget' => 26500000.00,
                'property_type' => '4 BHK Skyline Residence',
                'requirement' => 'BOOKED! ₹10,00,000 Booking Cheque received. Unit 1801 confirmed.',
                'status' => 'closed_won',
                'current_stage' => 'closed_won',
                'lead_score' => 100,
                'assigned_agent_idx' => 1, // Priya Patel
                'project' => $project2,
                'campaign' => $campaign2,
                'source' => $googleSource,
                'created_at' => now()->subDays(12)->subHours(4),
                'first_response_at' => now()->subDays(12)->subHours(3)->addMinutes(45),
                'cost' => 20.00,
            ],
            [
                'lead_code' => 'LP-2026-00003416',
                'name' => 'Vandana Khurana',
                'mobile' => '+919811223416',
                'email' => 'vandana.khurana@pwc.com',
                'city' => 'Noida',
                'location' => 'Sector 150',
                'budget' => 11500000.00,
                'property_type' => '3 BHK Luxury Tower C',
                'requirement' => 'CLOSED WON! BBA (Builder Buyer Agreement) signed. Loan sanctioned.',
                'status' => 'closed_won',
                'current_stage' => 'closed_won',
                'lead_score' => 100,
                'assigned_agent_idx' => 2, // Vikram Malhotra
                'project' => $project1,
                'campaign' => $campaign1,
                'source' => $metaSource,
                'created_at' => now()->subDays(13)->subHours(5),
                'first_response_at' => now()->subDays(13)->subHours(4)->addMinutes(40),
                'cost' => 15.00,
            ],

            // ───────────── UNASSIGNED, REPLACEMENT & PIPELINE (4 leads) ─────────────
            [
                'lead_code' => 'LP-2026-00003417',
                'name' => 'Tarun Bajaj',
                'mobile' => '+919811223417',
                'email' => 'tarun.bajaj@gmail.com',
                'city' => 'Bengaluru',
                'location' => 'Whitefield',
                'budget' => 5500000.00,
                'property_type' => 'Office Space',
                'requirement' => 'Fresh portal inquiry awaiting sales executive round-robin allocation.',
                'status' => 'unassigned',
                'current_stage' => 'new',
                'lead_score' => 70,
                'assigned_agent_idx' => null, // Unassigned
                'project' => $project3,
                'campaign' => $campaign3,
                'source' => $portalSource,
                'created_at' => now()->subDays(14)->subHours(3),
                'first_response_at' => null,
                'cost' => 12.00,
            ],
            [
                'lead_code' => 'LP-2026-00003418',
                'name' => 'Rakesh Nair',
                'mobile' => '+919811223418',
                'email' => 'rakesh.nair@rediffmail.com',
                'city' => 'Noida',
                'location' => 'Sector 150',
                'budget' => 9200000.00,
                'property_type' => '3 BHK Apartment',
                'requirement' => 'Wrong telephone contact provided in Meta leadform. Replacement approved.',
                'status' => 'replacement',
                'current_stage' => 'lost',
                'lead_score' => 10,
                'assigned_agent_idx' => 3, // Sneha Reddy
                'project' => $project1,
                'campaign' => $campaign1,
                'source' => $metaSource,
                'created_at' => now()->subDays(14)->subHours(7),
                'first_response_at' => now()->subDays(14)->subHours(6)->addMinutes(45),
                'cost' => 15.00,
            ],
            [
                'lead_code' => 'LP-2026-00003419',
                'name' => 'Sumit Saxena',
                'mobile' => '+919811223419',
                'email' => 'sumit.saxena@tcs.com',
                'city' => 'Noida',
                'location' => 'Sector 150',
                'budget' => 10500000.00,
                'property_type' => '3 BHK Tower A',
                'requirement' => 'Channel partner referral walk-in. Contacted and requested brochure.',
                'status' => 'contacted',
                'current_stage' => 'contacted',
                'lead_score' => 82,
                'assigned_agent_idx' => 0, // Aman Sharma
                'project' => $project1,
                'campaign' => $campaign4,
                'source' => $manualSource,
                'created_at' => now()->subDays(15)->subHours(2),
                'first_response_at' => now()->subDays(15)->subHours(1)->addMinutes(45),
                'cost' => 15.00,
            ],
            [
                'lead_code' => 'LP-2026-00003420',
                'name' => 'Divya Deshmukh',
                'mobile' => '+919811223420',
                'email' => 'divya.deshmukh@deloitte.com',
                'city' => 'Gurgaon',
                'location' => 'Golf Course Extn',
                'budget' => 22000000.00,
                'property_type' => '4 BHK Premium',
                'requirement' => 'Detailed conversation had. Awaiting family consultation next weekend.',
                'status' => 'contacted',
                'current_stage' => 'contacted',
                'lead_score' => 85,
                'assigned_agent_idx' => 1, // Priya Patel
                'project' => $project2,
                'campaign' => $campaign2,
                'source' => $googleSource,
                'created_at' => now()->subDays(15)->subHours(5),
                'first_response_at' => now()->subDays(15)->subHours(4)->addMinutes(40),
                'cost' => 20.00,
            ],
        ];

        $runningBalance = 1500.00;
        $totalCreditsConsumed = 0.00;
        $createdLeads = [];

        foreach ($leadsDefinitions as $def) {
            $assignedUser = $def['assigned_agent_idx'] !== null ? $teamAgents[$def['assigned_agent_idx']] : null;

            $lead = Lead::create([
                'lead_code' => $def['lead_code'],
                'client_id' => $builderUser->id,
                'project_id' => $def['project']->id,
                'campaign_id' => $def['campaign']->id,
                'lead_source_id' => $def['source']->id,
                'name' => $def['name'],
                'mobile' => $def['mobile'],
                'email' => $def['email'],
                'city' => $def['city'],
                'location' => $def['location'],
                'budget' => $def['budget'],
                'property_type' => $def['property_type'],
                'requirement' => $def['requirement'],
                'status' => $def['status'],
                'current_stage' => $def['current_stage'],
                'lead_score' => $def['lead_score'],
                'assigned_to' => $assignedUser?->id,
                'first_response_at' => $def['first_response_at'],
                'lead_cost' => $def['cost'],
                'created_at' => $def['created_at'],
                'updated_at' => $def['created_at'],
            ]);

            $createdLeads[] = $lead;

            // Record Lead Assignment
            if ($assignedUser) {
                LeadAssignment::create([
                    'lead_id' => $lead->id,
                    'assigned_to' => $assignedUser->id,
                    'assigned_by' => $builderUser->id,
                    'assigned_at' => $def['created_at']->copy()->addMinutes(5),
                    'created_at' => $def['created_at']->copy()->addMinutes(5),
                ]);
            }

            // Deduct Lead Delivery Credits in ledger
            $cost = $def['cost'];
            $before = $runningBalance;
            $runningBalance = max(0.00, $runningBalance - $cost);
            $totalCreditsConsumed += $cost;

            CreditTransaction::create([
                'client_id' => $builderUser->id,
                'lead_id' => $lead->id,
                'package_id' => null,
                'credit_before' => $before,
                'credit_used' => $cost,
                'credit_after' => $runningBalance,
                'transaction_type' => 'lead_consumption',
                'description' => "Lead delivery charge for {$lead->lead_code} ({$lead->name})",
                'created_by' => 1,
                'created_at' => $def['created_at'],
            ]);
        }

        // Add 1 Lead Replacement & Refund for LP-2026-00003418
        $replacementLead = collect($createdLeads)->firstWhere('lead_code', 'LP-2026-00003418');
        if ($replacementLead) {
            $invalidNumberReasonId = DB::table('replacement_reasons')->where('is_eligible', 1)->value('id') ?? 5;

            LeadReplacement::create([
                'lead_id' => $replacementLead->id,
                'reason_id' => $invalidNumberReasonId,
                'requested_by' => $builderUser->id,
                'requested_at' => now()->subDays(13),
                'sla_met' => true,
                'status' => 'approved',
                'resolved_at' => now()->subDays(12),
                'created_at' => now()->subDays(13),
            ]);

            $replacementLead->update(['replacement_status' => 'approved']);

            // Refund 15 credits back to wallet
            $before = $runningBalance;
            $runningBalance += 15.00;

            CreditTransaction::create([
                'client_id' => $builderUser->id,
                'lead_id' => $replacementLead->id,
                'package_id' => null,
                'credit_before' => $before,
                'credit_used' => 15.00,
                'credit_after' => $runningBalance,
                'transaction_type' => 'refund',
                'description' => "Replacement refund approved for {$replacementLead->lead_code} (Invalid Phone Number)",
                'created_by' => 1,
                'created_at' => now()->subDays(12),
            ]);
        }

        // Update Wallet Balance
        $wallet->update([
            'opening_balance' => 500.00,
            'balance' => $runningBalance,
        ]);

        // ─────────────────────────────────────────────────────────────
        // 10. Seed Follow-ups (Due Today, Overdue, Upcoming, Completed)
        // ─────────────────────────────────────────────────────────────
        $leadsByCode = collect($createdLeads)->keyBy('lead_code');

        $followupsData = [
            // Due Today 1: Shalini Gupta (Site visit)
            [
                'lead_code' => 'LP-2026-00003410',
                'due_at' => now()->setTime(15, 0, 0),
                'note' => "Conduct site visit tour of sample 3 BHK flat. Client arriving with family.",
                'status' => 'pending',
                'user' => $teamAgents[0],
            ],
            // Due Today 2: Alok Nath Tandon (Negotiation discussion)
            [
                'lead_code' => 'LP-2026-00003413',
                'due_at' => now()->setTime(17, 30, 0),
                'note' => "Call back to discuss 50:50 payment plan and confirm token booking.",
                'status' => 'pending',
                'user' => $teamAgents[3],
            ],
            // Overdue: Deepak Chawla (Due yesterday)
            [
                'lead_code' => 'LP-2026-00003406',
                'due_at' => now()->subDay()->setTime(16, 0, 0),
                'note' => "Send revised floor plans of ground floor commercial unit via WhatsApp.",
                'status' => 'pending',
                'user' => $teamAgents[0],
            ],
            // Upcoming: Dr. Pradeep Joshi (Due in 2 days)
            [
                'lead_code' => 'LP-2026-00003402',
                'due_at' => now()->addDays(2)->setTime(11, 0, 0),
                'note' => "Follow-up regarding weekend site visit confirmation.",
                'status' => 'pending',
                'user' => $teamAgents[0],
            ],
            // Upcoming: Abhinav Singhania (Due in 3 days)
            [
                'lead_code' => 'LP-2026-00003404',
                'due_at' => now()->addDays(3)->setTime(14, 0, 0),
                'note' => "Schedule meeting at NRR head office to review penthouse architectural layout.",
                'status' => 'pending',
                'user' => $teamAgents[2],
            ],
            // Completed 1: Kavita Sen
            [
                'lead_code' => 'LP-2026-00003407',
                'due_at' => now()->subDays(3)->setTime(12, 0, 0),
                'note' => "Shared project e-brochure and price list on WhatsApp. Client confirmed interest.",
                'status' => 'completed',
                'user' => $teamAgents[1],
            ],
            // Completed 2: Sanjay Agarwal
            [
                'lead_code' => 'LP-2026-00003411',
                'due_at' => now()->subDays(1)->setTime(16, 30, 0),
                'note' => "Completed site tour of Golf Course property. Client liked 4 BHK layout.",
                'status' => 'completed',
                'user' => $teamAgents[1],
            ],
            // Completed 3: Rohitashv Oberoi
            [
                'lead_code' => 'LP-2026-00003415',
                'due_at' => now()->subDays(2)->setTime(15, 0, 0),
                'note' => "Booking amount received. Verified KYC documents & PAN card copy.",
                'status' => 'completed',
                'user' => $teamAgents[1],
            ],
        ];

        foreach ($followupsData as $fData) {
            $l = $leadsByCode->get($fData['lead_code']);
            if ($l) {
                Followup::create([
                    'lead_id' => $l->id,
                    'due_at' => $fData['due_at'],
                    'note' => $fData['note'],
                    'status' => $fData['status'],
                    'created_by' => $fData['user']->id,
                    'created_at' => $fData['due_at']->copy()->subDays(1),
                ]);
            }
        }

        // ─────────────────────────────────────────────────────────────
        // 11. Seed Call Logs for Realistic Telephony Activity
        // ─────────────────────────────────────────────────────────────
        $callsData = [
            [
                'lead_code' => 'LP-2026-00003403',
                'agent' => $teamAgents[1],
                'duration' => 184,
                'outcome' => 'connected',
                'called_at' => now()->subHours(5)->addMinutes(45),
            ],
            [
                'lead_code' => 'LP-2026-00003404',
                'agent' => $teamAgents[2],
                'duration' => 245,
                'outcome' => 'connected',
                'called_at' => now()->subHours(7)->addMinutes(42),
            ],
            [
                'lead_code' => 'LP-2026-00003407',
                'agent' => $teamAgents[1],
                'duration' => 312,
                'outcome' => 'connected',
                'called_at' => now()->subDays(4)->subHours(1),
            ],
            [
                'lead_code' => 'LP-2026-00003410',
                'agent' => $teamAgents[0],
                'duration' => 195,
                'outcome' => 'connected',
                'called_at' => now()->subDays(7)->subHours(2),
            ],
            [
                'lead_code' => 'LP-2026-00003411',
                'agent' => $teamAgents[1],
                'duration' => 278,
                'outcome' => 'connected',
                'called_at' => now()->subDays(8)->subHours(4),
            ],
            [
                'lead_code' => 'LP-2026-00003413',
                'agent' => $teamAgents[3],
                'duration' => 360,
                'outcome' => 'connected',
                'called_at' => now()->subDays(10)->subHours(1),
            ],
            [
                'lead_code' => 'LP-2026-00003415',
                'agent' => $teamAgents[1],
                'duration' => 420,
                'outcome' => 'connected',
                'called_at' => now()->subDays(12)->subHours(3),
            ],
        ];

        foreach ($callsData as $c) {
            $l = $leadsByCode->get($c['lead_code']);
            if ($l) {
                LeadCall::create([
                    'lead_id' => $l->id,
                    'user_id' => $c['agent']->id,
                    'duration_seconds' => $c['duration'],
                    'recording_url' => 'https://recordings.leadpanther.com/calls/nrr_' . $l->id . '_' . time() . '.mp3',
                    'outcome' => $c['outcome'],
                    'called_at' => $c['called_at'],
                    'created_at' => $c['called_at'],
                ]);
            }
        }

        // ─────────────────────────────────────────────────────────────
        // 12. Seed Audit Trail for Organization 34
        // ─────────────────────────────────────────────────────────────
        $auditLogs = [
            [
                'action' => 'organization.created',
                'from_value' => null,
                'to_value' => json_encode(['id' => 34, 'name' => 'NRR', 'status' => 'active']),
                'created_at' => now()->subDays(25),
            ],
            [
                'action' => 'credit.recharge',
                'from_value' => '500.00 Cr',
                'to_value' => '1500.00 Cr',
                'created_at' => now()->subDays(12),
            ],
            [
                'action' => 'project.created',
                'from_value' => null,
                'to_value' => json_encode(['name' => 'NRR Green Valley Phase 1', 'rate' => '15.00 Cr']),
                'created_at' => now()->subDays(20),
            ],
            [
                'action' => 'team.members_assigned',
                'from_value' => null,
                'to_value' => 'Aman Sharma, Priya Patel, Vikram Malhotra, Sneha Reddy',
                'created_at' => now()->subDays(18),
            ],
            [
                'action' => 'replacement.approved',
                'from_value' => 'LP-2026-00003418 Pending',
                'to_value' => 'Approved (15.00 Cr Refunded)',
                'created_at' => now()->subDays(12),
            ],
        ];

        foreach ($auditLogs as $a) {
            AuditLog::create([
                'user_id' => 1,
                'action' => $a['action'],
                'subject_type' => Organization::class,
                'subject_id' => 34,
                'from_value' => $a['from_value'],
                'to_value' => $a['to_value'],
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) LeadPanther/2.0',
                'created_at' => $a['created_at'],
            ]);
        }

        $this->command?->info("✓ Successfully seeded Organization ID 34 (NRR):");
        $this->command?->info("  - 1 Primary Builder User: NRR (nrr@leadpanther.crm)");
        $this->command?->info("  - 4 Dedicated Sales Executives (Aman, Priya, Vikram, Sneha)");
        $this->command?->info("  - 3 Projects (NRR Green Valley, NRR Skyline Towers, NRR Silicon Hub)");
        $this->command?->info("  - 4 Campaigns (Meta, Google Search, Portal, Manual)");
        $this->command?->info("  - 20 Assigned Sample Leads across all lifecycle stages");
        $this->command?->info("  - Credit Wallet: 1,210.00 Cr available (Opening: 500.00 Cr, Consumed: 305.00 Cr, Recharged: 1,000.00 Cr, Refund: 15.00 Cr)");
        $this->command?->info("  - 8 Follow-ups (2 due today, 1 overdue, 2 upcoming, 3 completed)");
        $this->command?->info("  - 7 Verified Call Logs & Complete Audit History");
    }
}
