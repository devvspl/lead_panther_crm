<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Organization;
use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectUnit;

class ClientProjectSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::where('type', 'builder')->first();

        $clientNames = ['Apex Real Estate Ventures', 'Prestige Landmark Group', 'Sobha Urban Living'];

        foreach ($clientNames as $index => $cName) {
            $client = Client::create([
                'organization_id' => $org->id,
                'name' => $cName,
                'billing_email' => 'billing@' . strtolower(str_replace(' ', '', $cName)) . '.com',
                'status' => 'active',
            ]);

            $projectCount = rand(1, 3);
            for ($p = 1; $p <= $projectCount; $p++) {
                $project = Project::create([
                    'client_id' => $client->id,
                    'name' => $cName . ' Phase ' . $p,
                    'location' => rand(0, 1) ? 'Whitefield, Bengaluru' : 'Bandra West, Mumbai',
                    'property_type' => rand(0, 1) ? 'Residential Apartments' : 'Luxury Villas',
                    'price_range_min' => 6500000.00,
                    'price_range_max' => 22000000.00,
                    'status' => 'active',
                ]);

                $unitCount = rand(10, 30);
                for ($u = 1; $u <= $unitCount; $u++) {
                    ProjectUnit::create([
                        'project_id' => $project->id,
                        'unit_number' => 'U-' . (100 + $u),
                        'type' => ($u % 2 === 0) ? '3 BHK' : '2 BHK',
                        'price' => rand(70, 150) * 100000,
                        'status' => rand(0, 1) ? 'available' : 'booked',
                    ]);
                }
            }
        }
    }
}
