<?php

namespace Tests\Feature\Seeders;

use App\Models\Campaign;
use App\Models\Lead;
use App\Models\LeadMetadata;
use App\Models\LeadSource;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\FilterTruegrowthLeadsByCreatedDateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FilterTruegrowthLeadsByCreatedDateSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_keeps_only_25_26_27_date_leads_on_truegrowth_and_revokes_others(): void
    {
        $role = Role::findOrCreate('Builder/Developer');

        // Create organizations
        $sourceOrg = Organization::create([
            'name' => 'Gulmohar Studio 135',
            'status' => 'active',
        ]);
        $targetOrg = Organization::create([
            'id' => 43,
            'name' => 'Truegrowth Realty',
            'status' => 'active',
        ]);

        // Create clients
        $sourceClient = User::factory()->create([
            'organization_id' => $sourceOrg->id,
            'name' => 'Gulmohar Developer',
        ]);
        $sourceClient->assignRole($role);

        $targetClient = User::factory()->create([
            'organization_id' => $targetOrg->id,
            'name' => 'Truegrowth Owner',
        ]);
        $targetClient->assignRole($role);

        // Create projects and campaigns
        $sourceProject = Project::create([
            'client_id' => $sourceClient->id,
            'name' => 'Gulmohar Studio Project',
            'status' => 'active',
        ]);
        $sourceCampaign = Campaign::create([
            'project_id' => $sourceProject->id,
            'name' => 'Studio Noida Exp 50 – 2 gulmohar',
            'status' => 'active',
        ]);

        $targetProject = Project::create([
            'client_id' => $targetClient->id,
            'name' => 'Studio Buy Leads',
            'status' => 'active',
        ]);
        $targetCampaign = Campaign::create([
            'project_id' => $targetProject->id,
            'name' => 'Studio Noida Exp 50 – 2 gulmohar',
            'status' => 'active',
        ]);

        $source = LeadSource::create(['name' => 'Meta']);

        // Lead 1: Created Nov 24 (outside permitted dates) - currently on Truegrowth Realty -> MUST BE REVOKED
        $leadNov24 = Lead::create([
            'lead_code' => 'LP-DATE-24',
            'name' => 'Nov 24 Lead',
            'mobile' => '9800000024',
            'client_id' => $targetClient->id,
            'campaign_id' => $targetCampaign->id,
            'lead_source_id' => $source->id,
            'status' => 'new',
            'current_stage' => 'new',
            'delivery_pending' => false,
            'internal_processing_pending' => false,
            'created_at' => Carbon::parse('2026-11-24 14:30:00'),
        ]);
        LeadMetadata::create([
            'lead_id' => $leadNov24->id,
            'key' => 'want_to_buy_studio',
            'value' => 'buy',
        ]);

        // Lead 2: Created Nov 25 (eligible date) -> MUST BE KEPT on Truegrowth
        $leadNov25 = Lead::create([
            'lead_code' => 'LP-DATE-25',
            'name' => 'Nov 25 Lead',
            'mobile' => '9800000025',
            'client_id' => $targetClient->id,
            'campaign_id' => $targetCampaign->id,
            'lead_source_id' => $source->id,
            'status' => 'new',
            'current_stage' => 'new',
            'delivery_pending' => false,
            'internal_processing_pending' => false,
            'created_at' => Carbon::parse('2026-11-25 10:15:00'),
        ]);
        LeadMetadata::create([
            'lead_id' => $leadNov25->id,
            'key' => 'want_to_buy_studio',
            'value' => 'buy',
        ]);

        // Lead 3: Created Nov 26 (eligible date) - currently in Gulmohar -> MUST BE PASSED to Truegrowth
        $leadNov26 = Lead::create([
            'lead_code' => 'LP-DATE-26',
            'name' => 'Nov 26 Lead',
            'mobile' => '9800000026',
            'client_id' => $sourceClient->id,
            'campaign_id' => $sourceCampaign->id,
            'lead_source_id' => $source->id,
            'status' => 'new',
            'current_stage' => 'new',
            'delivery_pending' => false,
            'internal_processing_pending' => false,
            'created_at' => Carbon::parse('2026-11-26 12:00:00'),
        ]);
        LeadMetadata::create([
            'lead_id' => $leadNov26->id,
            'key' => 'want_to_buy_studio',
            'value' => 'buy',
        ]);

        // Lead 4: Created Nov 27 (eligible date) -> MUST BE KEPT on Truegrowth
        $leadNov27 = Lead::create([
            'lead_code' => 'LP-DATE-27',
            'name' => 'Nov 27 Lead',
            'mobile' => '9800000027',
            'client_id' => $targetClient->id,
            'campaign_id' => $targetCampaign->id,
            'lead_source_id' => $source->id,
            'status' => 'new',
            'current_stage' => 'new',
            'delivery_pending' => false,
            'internal_processing_pending' => false,
            'created_at' => Carbon::parse('2026-11-27 16:45:00'),
        ]);
        LeadMetadata::create([
            'lead_id' => $leadNov27->id,
            'key' => 'want_to_buy_studio',
            'value' => 'buy',
        ]);

        // Lead 5: Created Nov 26 but want_to_buy_studio = rent (Not Buy) -> MUST NOT be in Truegrowth
        $leadNov26Rent = Lead::create([
            'lead_code' => 'LP-DATE-26-RENT',
            'name' => 'Nov 26 Rent Lead',
            'mobile' => '9800000028',
            'client_id' => $targetClient->id,
            'campaign_id' => $targetCampaign->id,
            'lead_source_id' => $source->id,
            'status' => 'new',
            'current_stage' => 'new',
            'delivery_pending' => false,
            'internal_processing_pending' => false,
            'created_at' => Carbon::parse('2026-11-26 18:00:00'),
        ]);
        LeadMetadata::create([
            'lead_id' => $leadNov26Rent->id,
            'key' => 'want_to_buy_studio',
            'value' => 'rent',
        ]);

        // Run Seeder
        $seeder = new FilterTruegrowthLeadsByCreatedDateSeeder();
        $seeder->run();

        // Assertions:
        // 1. LeadNov24 was revoked and returned to Gulmohar
        $leadNov24->refresh();
        $this->assertEquals($sourceClient->id, $leadNov24->client_id, 'Nov 24 lead should be reverted to Gulmohar source client');
        $this->assertDatabaseHas('lead_metadata', [
            'lead_id' => $leadNov24->id,
            'key' => 'revoked_from_organization',
            'value' => 'Truegrowth Realty',
        ]);

        // 2. LeadNov25 was kept on Truegrowth
        $leadNov25->refresh();
        $this->assertEquals($targetClient->id, $leadNov25->client_id, 'Nov 25 Buy lead should be kept on Truegrowth');

        // 3. LeadNov26 was passed to Truegrowth
        $leadNov26->refresh();
        $this->assertEquals($targetClient->id, $leadNov26->client_id, 'Nov 26 Buy lead should be passed to Truegrowth');

        // 4. LeadNov27 was kept on Truegrowth
        $leadNov27->refresh();
        $this->assertEquals($targetClient->id, $leadNov27->client_id, 'Nov 27 Buy lead should be kept on Truegrowth');

        // 5. LeadNov26Rent was revoked and returned to Gulmohar
        $leadNov26Rent->refresh();
        $this->assertEquals($sourceClient->id, $leadNov26Rent->client_id, 'Rent lead should be reverted to Gulmohar');

        // 6. Verify total Truegrowth leads count is exactly 3 (Nov 25, 26, 27 Buy leads)
        $truegrowthTotal = Lead::withoutGlobalScopes()->where('client_id', $targetClient->id)->count();
        $this->assertEquals(3, $truegrowthTotal);
    }
}
