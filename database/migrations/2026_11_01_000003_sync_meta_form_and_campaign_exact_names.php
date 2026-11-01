<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\LeadFormMapping;
use App\Models\Project;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\LeadSource;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $leadSource = LeadSource::firstOrCreate(['name' => 'meta']);
        $defaultClientId = Client::first()?->id ?? (\App\Models\User::first()?->id ?? 1);

        // 1. Strip unwanted " Campaign" suffix from existing campaigns
        $campaignsWithSuffix = Campaign::where('name', 'like', '% Campaign')->get();
        foreach ($campaignsWithSuffix as $camp) {
            $strippedName = preg_replace('/\s+Campaign$/', '', $camp->name);
            if (!empty($strippedName)) {
                $camp->update(['name' => $strippedName]);
            }
        }

        // 2. Synchronize all LeadFormMappings to have exact matching Project and Campaign
        $mappings = LeadFormMapping::all();
        foreach ($mappings as $mapping) {
            $fName = trim($mapping->form_name ?: '');
            if (empty($fName) || str_starts_with($fName, 'Lead Form #')) {
                continue;
            }

            $clientId = $mapping->client_id ?: ($mapping->project?->client_id ?? $defaultClientId);

            // Find or create Project with exact form name
            $project = Project::firstOrCreate(
                ['name' => $fName],
                [
                    'client_id' => $clientId,
                    'status' => 'active',
                ]
            );

            // Find or create Campaign with exact form name
            $campaign = Campaign::where('project_id', $project->id)
                ->where('name', $fName)
                ->first();

            if (!$campaign) {
                $campaign = Campaign::create([
                    'project_id' => $project->id,
                    'name' => $fName,
                    'lead_source_id' => $leadSource->id,
                    'utm_campaign' => Str::slug($fName),
                    'status' => 'active',
                ]);
            }

            $mapping->update([
                'project_id' => $project->id,
                'campaign_id' => $campaign->id,
                'client_id' => $clientId,
            ]);
        }
    }

    public function down(): void
    {
        // Irreversible name standardization
    }
};
