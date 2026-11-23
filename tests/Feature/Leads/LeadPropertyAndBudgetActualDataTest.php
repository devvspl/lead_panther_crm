<?php

namespace Tests\Feature\Leads;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Organization;
use App\Models\Client;
use App\Models\Project;
use App\Models\Lead;
use App\Models\LeadMetadata;
use App\Models\LeadSource;
use App\Livewire\Leads\LeadDetail;
use App\Support\LeadMappers\MetaLeadMapper;
use App\Services\MetaLeadSyncService;
use App\Support\LeadPropertyAndBudgetExtractor;
use Livewire\Livewire;

class LeadPropertyAndBudgetActualDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_lead_detail_displays_actual_meta_property_type_and_budget_instead_of_static_defaults(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('Super Admin');

        $org = Organization::create(['name' => 'H&S By Investors Clinic', 'status' => 'active']);
        $client = Client::create(['name' => 'H&S Client', 'organization_id' => $org->id, 'status' => 'active']);
        $project = Project::create(['name' => 'Boulevard walk by H&S', 'client_id' => $client->id]);
        $source = LeadSource::firstOrCreate(['name' => 'meta']);

        // A lead like Harvendra Yadav from the screenshot with the old static 75L default stored
        $lead = Lead::create([
            'lead_code' => Lead::generateUniqueLeadCode(),
            'client_id' => $client->id,
            'name' => 'Harvendra Yadav',
            'mobile' => '918218018817',
            'city' => 'Babarala',
            'budget' => 7500000.00, // Old hardcoded default
            'property_type' => '',   // Empty, previously fell back to 'Residential'
            'lead_source_id' => $source->id,
            'status' => 'new',
            'current_stage' => 'new',
        ]);

        // Attach Meta form submission answers
        LeadMetadata::create([
            'lead_id' => $lead->id,
            'key' => 'meta_form_answers',
            'value' => json_encode([
                'WHICH RETAIL SPACE SIZE ARE YOU INTERESTED IN' => '100 Sq. Ft. - ₹34.99 Lakh Est. Rental ₹29, 158/month',
                'DO YOU WANT TO BUY A COMMERCIAL RETAIL SPACE IN GREATER NOIDA WEST' => 'Rental Income',
                'WHEN WOULD YOU LIKE TO SCHEDULE YOUR SITE VISIT' => 'Within 15 Days',
            ]),
        ]);

        $component = Livewire::actingAs($admin)->test(LeadDetail::class)
            ->call('openLead', $lead->id);

        // Must display actual property type and actual budget
        $component->assertSee('Retail Space (100 Sq. Ft.)')
            ->assertDontSee('Residential')
            ->assertSee('₹34.99 Lakhs')
            ->assertDontSee('₹75.0 Lakhs')
            ->assertSee('₹35.0L')
            ->assertDontSee('₹75.0L');

        // Verify lead was self-healed in database
        $fresh = $lead->fresh();
        $this->assertEquals(3499000.00, (float) $fresh->budget);
        $this->assertEquals('Retail Space (100 Sq. Ft.)', $fresh->property_type);
    }

    public function test_lead_detail_shows_dash_when_property_type_and_budget_are_not_specified(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('Super Admin');

        $org = Organization::create(['name' => 'Sunrise Corp', 'status' => 'active']);
        $client = Client::create(['name' => 'Sunrise Client', 'organization_id' => $org->id, 'status' => 'active']);
        $source = LeadSource::firstOrCreate(['name' => 'meta']);

        $lead = Lead::create([
            'lead_code' => Lead::generateUniqueLeadCode(),
            'client_id' => $client->id,
            'lead_source_id' => $source->id,
            'name' => 'Blank Lead',
            'mobile' => '9876543210',
            'city' => 'Noida',
            'budget' => null,
            'property_type' => null,
            'status' => 'new',
            'current_stage' => 'new',
        ]);

        $component = Livewire::actingAs($admin)->test(LeadDetail::class)
            ->call('openLead', $lead->id);

        // Must NOT display 'Residential' or '₹0.0 Lakhs' or '₹75.0 Lakhs'
        $component->assertDontSee('Residential')
            ->assertDontSee('₹0.0 Lakhs')
            ->assertDontSee('₹75.0 Lakhs');

        // Actual accessors should return null
        $this->assertNull($lead->actual_property_type);
        $this->assertNull($lead->actual_budget);
        $this->assertEquals('—', $lead->budget_formatted);
        $this->assertEquals('—', $lead->budget_display);
    }

    public function test_meta_lead_mapper_extracts_actual_budget_and_property_type_from_meta_answers(): void
    {
        $mapper = new MetaLeadMapper();
        $mapped = $mapper->map([
            'full_name' => 'Harvendra Yadav',
            'phone_number' => '+918218018817',
            'field_data' => [
                [
                    'name' => 'WHICH RETAIL SPACE SIZE ARE YOU INTERESTED IN',
                    'values' => ['100 Sq. Ft. - ₹34.99 Lakh Est. Rental ₹29, 158/month'],
                ],
                [
                    'name' => 'DO YOU WANT TO BUY A COMMERCIAL RETAIL SPACE IN GREATER NOIDA WEST',
                    'values' => ['Rental Income'],
                ],
                [
                    'name' => 'city',
                    'values' => ['Babarala'],
                ],
            ],
        ]);

        $this->assertEquals(3499000, $mapped['budget']);
        $this->assertEquals('Retail Space (100 Sq. Ft.)', $mapped['property_type']);
        $this->assertEquals('Babarala', $mapped['city']);
        $this->assertEquals('Greater Noida West', $mapped['location']);
    }

    public function test_lead_details_storage_for_lead_with_call_time_and_visit_time_and_location(): void
    {
        $mapper = new MetaLeadMapper();
        $mapped = $mapper->map([
            'full_name' => 'Sandeep Kumar',
            'phone_number' => '919999999999',
            'field_data' => [
                ['name' => 'WHICH RETAIL SPACE SIZE ARE YOU INTERESTED IN', 'values' => ['100 Sq. Ft. — ₹34.99 Lakh Est. Rental ₹29, 158/month']],
                ['name' => 'SUITABLE TIME FOR CALL', 'values' => ['☎️ 10:00 AM – 12:00 PM']],
                ['name' => 'CITY', 'values' => ['Noida']],
                ['name' => 'WHEN WOULD YOU LIKE TO SCHEDULE YOUR SITE VISIT', 'values' => ['Within 15 Days']],
                ['name' => 'DO YOU WANT TO BUY A COMMERCIAL RETAIL SPACE IN GREATER NOIDA WEST', 'values' => ['Rental Income']],
            ],
            'campaign_name' => 'BW_01',
            'adset_name' => 'BW_01',
            'ad_name' => 'BW_01',
            'form_id' => '1857617518772178',
        ]);

        $this->assertEquals(3499000, $mapped['budget']);
        $this->assertEquals('Retail Space (100 Sq. Ft.)', $mapped['property_type']);
        $this->assertEquals('Noida', $mapped['city']);
        $this->assertEquals('Greater Noida West', $mapped['location']);
        $this->assertNull($mapped['email']);
    }

    public function test_meta_lead_sync_parse_budget_returns_null_instead_of_75_lakhs_for_missing_budget(): void
    {
        $this->assertEquals(3499000.00, MetaLeadSyncService::parseBudget('₹34.99 Lakh'));
        $this->assertEquals(15000000.00, MetaLeadSyncService::parseBudget('1.5 Cr'));
        $this->assertNull(MetaLeadSyncService::parseBudget(null));
        $this->assertNull(MetaLeadSyncService::parseBudget(''));
        $this->assertNull(MetaLeadSyncService::parseBudget('Not applicable'));
    }

    public function test_heal_lead_details_command(): void
    {
        $org = Organization::create(['name' => 'Investors Clinic', 'status' => 'active']);
        $client = Client::create(['name' => 'IC Client', 'organization_id' => $org->id, 'status' => 'active']);
        $source = LeadSource::firstOrCreate(['name' => 'meta']);

        $lead = Lead::create([
            'lead_code' => Lead::generateUniqueLeadCode(),
            'client_id' => $client->id,
            'lead_source_id' => $source->id,
            'name' => 'Stale Lead',
            'mobile' => '918218018817',
            'budget' => 7500000.00, // Stale default
            'property_type' => 'Residential', // Stale default
            'status' => 'new',
            'current_stage' => 'new',
        ]);

        LeadMetadata::create([
            'lead_id' => $lead->id,
            'key' => 'meta_form_answers',
            'value' => json_encode([
                'WHICH RETAIL SPACE SIZE ARE YOU INTERESTED IN' => '100 Sq. Ft. - ₹34.99 Lakh Est. Rental ₹29, 158/month',
            ]),
        ]);

        $this->artisan('leads:heal-meta-details')
            ->assertSuccessful();

        $fresh = $lead->fresh();
        $this->assertEquals(3499000.00, (float) $fresh->budget);
        $this->assertEquals('Retail Space (100 Sq. Ft.)', $fresh->property_type);
    }
}
