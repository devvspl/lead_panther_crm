<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Lead;
use App\Models\Client;
use App\Models\Project;
use App\Models\Campaign;
use App\Models\LeadSource;
use App\Models\User;
use App\Models\LeadAssignment;
use App\Models\LeadStatusHistory;

class LeadSeeder extends Seeder
{
    public function run(): void
    {
        $clients = Client::all();
        $projects = Project::all();
        $campaigns = Campaign::all();
        $sources = LeadSource::all();
        $salesExecs = User::role('sales-executive')->get();
        $adminUser = User::role('super-admin')->first();

        for ($i = 0; $i < 300; $i++) {
            $client = $clients->random();
            $clientProjects = $projects->where('client_id', $client->id);
            $project = $clientProjects->count() > 0 ? $clientProjects->random() : $projects->random();

            $projectCampaigns = $campaigns->where('project_id', $project->id);
            $campaign = $projectCampaigns->count() > 0 ? $projectCampaigns->random() : $campaigns->random();

            $assignedAgent = $salesExecs->random();

            $lead = Lead::factory()->create([
                'client_id' => $client->id,
                'project_id' => $project->id,
                'campaign_id' => $campaign->id,
                'lead_source_id' => $campaign->lead_source_id ?? $sources->random()->id,
                'assigned_to' => $assignedAgent->id,
            ]);

            // Create Lead Assignment record
            LeadAssignment::create([
                'lead_id' => $lead->id,
                'assigned_to' => $assignedAgent->id,
                'assigned_by' => $adminUser->id ?? $assignedAgent->id,
                'assigned_at' => $lead->created_at,
            ]);

            // Create Lead Status History record
            LeadStatusHistory::create([
                'lead_id' => $lead->id,
                'from_status' => 'new',
                'to_status' => $lead->status,
                'changed_by' => $assignedAgent->id,
                'changed_at' => $lead->created_at,
            ]);
        }
    }
}
