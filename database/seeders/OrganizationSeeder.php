<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Organization;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        // 1 Platform Org
        Organization::firstOrCreate(['name' => 'Lead Panther Platform HQ'], ['status' => 'active']);

        // 2 Builder Orgs
        Organization::firstOrCreate(['name' => 'Venture Realty Developers'], ['status' => 'active']);
        Organization::firstOrCreate(['name' => 'Skyline Heights Infra'], ['status' => 'active']);

        // 3 Channel-Partner Orgs
        Organization::firstOrCreate(['name' => 'Royal Realty Associates'], ['status' => 'active']);
        Organization::firstOrCreate(['name' => 'Prime Square Partners'], ['status' => 'active']);
        Organization::firstOrCreate(['name' => 'Urban Living Advisory'], ['status' => 'active']);
    }
}
