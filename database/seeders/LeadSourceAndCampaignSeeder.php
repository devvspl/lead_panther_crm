<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\LeadSource;
use App\Models\Project;
use App\Models\Campaign;

class LeadSourceAndCampaignSeeder extends Seeder
{
    public function run(): void
    {
        $sources = ['meta', 'google', 'portal', 'owned_portal', 'manual'];
        $sourceModels = [];

        foreach ($sources as $source) {
            $sourceModels[$source] = LeadSource::firstOrCreate(
                ['name' => $source],
                ['is_active' => true]
            );
        }

        $projects = Project::all();

        foreach ($projects as $project) {
            $campaignCount = rand(2, 4);
            for ($c = 1; $c <= $campaignCount; $c++) {
                $srcKey = $sources[array_rand($sources)];
                Campaign::create([
                    'project_id' => $project->id,
                    'lead_source_id' => $sourceModels[$srcKey]->id,
                    'name' => $project->name . ' - ' . ucfirst($srcKey) . ' Campaign ' . $c,
                    'utm_campaign' => strtolower($srcKey) . '_campaign_' . $c,
                    'budget' => rand(50, 300) * 1000,
                    'status' => 'active',
                    'starts_at' => now()->subDays(rand(10, 60)),
                    'ends_at' => now()->addDays(rand(15, 60)),
                ]);
            }
        }
    }
}
