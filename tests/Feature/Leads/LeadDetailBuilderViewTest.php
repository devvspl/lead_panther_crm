<?php

namespace Tests\Feature\Leads;

use App\Livewire\Leads\LeadDetail;
use App\Models\Lead;
use App\Models\LeadMetadata;
use App\Models\LeadSource;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Support\ClientFacingText;
use App\Support\LeadMappers\MetaLeadMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LeadDetailBuilderViewTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;
    protected User $builderUser;
    protected User $executiveUser;
    protected User $superAdmin;
    protected Project $project;
    protected LeadSource $metaSource;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Builder', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Sales Executive', 'guard_name' => 'web']);

        $this->org = Organization::create(['name' => 'Deltek Developments']);

        $this->builderUser = User::factory()->create([
            'name' => 'Deltek Admin',
            'email' => 'builder@deltek.com',
            'organization_id' => $this->org->id,
            'status' => 'active',
        ]);
        $this->builderUser->assignRole('Builder');

        $this->executiveUser = User::factory()->create([
            'name' => 'Deltek Sales Exec',
            'email' => 'sales@deltek.com',
            'organization_id' => $this->org->id,
            'status' => 'active',
        ]);
        $this->executiveUser->assignRole('Sales Executive');

        $this->superAdmin = User::factory()->create([
            'name' => 'Super Admin User',
            'email' => 'admin@leadpanther.com',
            'status' => 'active',
        ]);
        $this->superAdmin->assignRole('Super Admin');

        $this->project = Project::create([
            'name' => 'Elan Boulevard',
            'client_id' => $this->builderUser->id,
        ]);

        $this->metaSource = LeadSource::firstOrCreate(['name' => 'meta']);
    }

    public function test_builder_view_does_not_show_raw_meta_requirement_or_inbox_urls_in_stage_actions(): void
    {
        \Illuminate\Support\Facades\Event::fake();

        $rawRequirement = "Meta Lead Submission: • City: Delhi • Suitable Time For Call: ☎️ 10:00 AM - 12:00 PM • When Would You Like To Schedule Your Site Visit: Within 30 Days • Do You Want To Buy A Commercial Retail Space In Greater Noida West: Rental Income • Inbox Url: https://business.facebook.com/latest/41338243345775109?nav_Ref=thread_View_By_Psid • Meta Context: Campaign: BW_01 | AdSet: BW_01 | Ad: BW_01 | Form: #1857617518772178";

        $lead = Lead::create([
            'lead_code' => 'LP-BUILDER-' . uniqid(),
            'project_id' => $this->project->id,
            'client_id' => $this->builderUser->id,
            'assigned_to' => $this->builderUser->id,
            'lead_source_id' => $this->metaSource->id,
            'name' => 'Mukesh',
            'mobile' => '91981898548',
            'current_stage' => 'assigned',
            'status' => 'assigned',
            'delivered_at' => now(),
            'delivery_pending' => false,
            'internal_processing_pending' => false,
            'requirement' => $rawRequirement,
        ]);

        LeadMetadata::create([
            'lead_id' => $lead->id,
            'key' => 'meta_form_answers',
            'value' => json_encode([
                'city' => 'Delhi',
                'suitable_time_for_call' => '☎️ 10:00 AM - 12:00 PM',
                'when_would_you_like_to_schedule_your_site_visit' => 'Within 30 Days',
                'do_you_want_to_buy_a_commercial_retail_space_in_greater_noida_west' => 'Rental Income',
                'which_retail_space_size_are_you_interested_in' => '100 Sq. Ft. — ₹34.99 Lakh Est. Rental ₹29, 158/month',
            ]),
        ]);

        $this->actingAs($this->builderUser);

        Livewire::test(LeadDetail::class)
            ->call('openLead', $lead->id)
            ->assertSet('isOpen', true)
            // Should show structured Q&A and customer info
            ->assertSee('Meta Lead Submission')
            ->assertSee('Suitable Time For Call')
            ->assertSee('☎️ 10:00 AM - 12:00 PM')
            ->assertSee('Within 30 Days')
            // Should NOT display raw requirement dump in Stage Actions
            ->assertDontSee('Requirements: Meta Lead Submission:')
            // Should NOT display internal Facebook Business Manager URLs or Inbox URLs
            ->assertDontSee('Inbox Url:')
            ->assertDontSee('business.facebook.com')
            ->assertDontSee('thread_View_By_Psid')
            // Should show the reassign / assign button
            ->assertSee('Assign Lead');
    }

    public function test_client_facing_text_strips_internal_meta_details_for_builder(): void
    {
        $text = "Meta Lead Submission: • Suitable Time: 10 AM • Inbox Url: https://business.facebook.com/latest/12345?nav=ref • Meta Context: Campaign: BW_01 | AdSet: BW_01 | Ad: BW_01 | Form: #1857617518772178";

        // Builder user should have internal URLs & Meta context stripped
        $sanitizedForBuilder = ClientFacingText::label($text, $this->builderUser);
        $this->assertStringNotContainsString('business.facebook.com', $sanitizedForBuilder);
        $this->assertStringNotContainsString('Inbox Url', $sanitizedForBuilder);
        $this->assertStringNotContainsString('Meta Context', $sanitizedForBuilder);
        $this->assertStringContainsString('Suitable Time: 10 AM', $sanitizedForBuilder);

        // Super Admin retains original text for audit / technical review
        $textForAdmin = ClientFacingText::label($text, $this->superAdmin);
        $this->assertStringContainsString('business.facebook.com', $textForAdmin);
        $this->assertStringContainsString('Meta Context: Campaign: BW_01', $textForAdmin);
    }

    public function test_meta_lead_mapper_ignores_inbox_url_parameter(): void
    {
        $mapper = new MetaLeadMapper();
        $payload = [
            'full_name' => 'Mukesh',
            'phone_number' => '+91981898548',
            'field_data' => [
                ['name' => 'city', 'values' => ['Delhi']],
                ['name' => 'inbox_url', 'values' => ['https://business.facebook.com/latest/41338243345775109?nav_Ref=thread_View_By_Psid']],
                ['name' => 'suitable_time_for_call', 'values' => ['10:00 AM - 12:00 PM']],
            ],
            'campaign_name' => 'BW_01',
            'adset_name' => 'BW_01',
            'ad_name' => 'BW_01',
            'form_id' => '1857617518772178',
        ];

        $mapped = $mapper->map($payload);

        $this->assertArrayNotHasKey('inbox_url', $mapped['custom_fields']);
        $this->assertStringNotContainsString('inbox_url', $mapped['requirement']);
        $this->assertStringNotContainsString('business.facebook.com', $mapped['requirement']);
    }
}
