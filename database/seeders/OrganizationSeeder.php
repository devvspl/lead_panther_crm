<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Organization;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        // 1 Platform Org
        Organization::firstOrCreate(['name' => 'Lead Panther Platform HQ'], ['type' => 'platform', 'status' => 'active']);

        // 2 Builder Orgs
        Organization::firstOrCreate(['name' => 'Venture Realty Developers'], ['type' => 'builder', 'status' => 'active']);
        Organization::firstOrCreate(['name' => 'Skyline Heights Infra'], ['type' => 'builder', 'status' => 'active']);

        // 3 Channel-Partner Orgs
        Organization::firstOrCreate(['name' => 'Royal Realty Associates'], ['type' => 'channel_partner', 'status' => 'active']);
        Organization::firstOrCreate(['name' => 'Prime Square Partners'], ['type' => 'channel_partner', 'status' => 'active']);
        Organization::firstOrCreate(['name' => 'Urban Living Advisory'], ['type' => 'channel_partner', 'status' => 'active']);
    }
}
