<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Lead;
use App\Models\LeadCommunication;

class CommunicationSeeder extends Seeder
{
    public function run(): void
    {
        $leads = Lead::all();

        foreach ($leads as $lead) {
            $commCount = rand(1, 4);
            for ($c = 0; $c < $commCount; $c++) {
                LeadCommunication::factory()->create([
                    'lead_id' => $lead->id,
                    'client_id' => $lead->client_id,
                    'user_id' => $lead->assigned_to,
                ]);
            }
        }
    }
}
