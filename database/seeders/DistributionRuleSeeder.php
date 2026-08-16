<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Project;
use App\Models\DistributionRule;

class DistributionRuleSeeder extends Seeder
{
    public function run(): void
    {
        $projects = Project::all();

        foreach ($projects as $project) {
            DistributionRule::create([
                'project_id' => $project->id,
                'rule_type' => 'round_robin',
                'config' => json_encode([
                    'max_leads_per_agent' => 50,
                    'auto_reassign_hours' => 24,
                    'working_hours' => ['start' => '09:00', 'end' => '18:00'],
                ]),
                'is_active' => true,
            ]);
        }
    }
}
