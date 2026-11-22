<?php

namespace Tests\Feature\Leads;

use App\Livewire\Leads\LeadDetail;
use App\Models\Lead;
use App\Models\LeadMetadata;
use App\Models\LeadSource;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LeadDetailMetaSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected LeadSource $metaSource;

    protected function setUp(): void
    {
        parent::setUp();

        $org = Organization::create(['name' => 'Test Org']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $this->user = User::factory()->create([
            'organization_id' => $org->id,
        ]);
        $this->user->assignRole('Super Admin');
        $this->actingAs($this->user);

        $this->metaSource = LeadSource::firstOrCreate(['name' => 'meta']);
    }

    public function test_renders_meta_questions_and_answers_separately_with_hindi_and_symbols(): void
    {
        $lead = Lead::factory()->create([
            'lead_source_id' => $this->metaSource->id,
            'name' => 'Harsh Kant Saxena',
            'mobile' => '918003476637',
            'requirement' => 'Meta Lead Submission: • Test: Yes',
        ]);

        LeadMetadata::create([
            'lead_id' => $lead->id,
            'key' => 'meta_form_answers',
            'value' => json_encode([
                'would_you_like_to_schedule_a_site_visit_to_dholera?' => 'Yes, I Want A Site Visit',
                'full_name' => 'Harsh Kant Saxena',
                'when_are_you_planning_to_purchase?' => 'Immediately (0–30 Days)',
                'phone_number' => '+918003476637',
                'suitable_time_for_call_☎️_' => '12 Noon To 4 PM',
                'which_type_of_plot_are_you_interested_in_at_dholera?' => '488 Sq. Yard Commercial Plot ₹moneyplant: 155–600 Sq. Yard × ₹11, 500 = ₹17.83 लाख से ₹69 लाख कुल निवेश।/sq. Yard Total Investment: ₹19.5 Lakh Approx.',
            ]),
        ]);

        LeadMetadata::create([
            'lead_id' => $lead->id,
            'key' => 'meta_campaign_name',
            'value' => 'SIR Inside Plots 1- Palacio',
        ]);
        LeadMetadata::create([
            'lead_id' => $lead->id,
            'key' => 'meta_adset_name',
            'value' => 'SIR Inside Plots',
        ]);
        LeadMetadata::create([
            'lead_id' => $lead->id,
            'key' => 'meta_ad_name',
            'value' => 'SIR Inside Plots Creative A',
        ]);
        LeadMetadata::create([
            'lead_id' => $lead->id,
            'key' => 'meta_form_name',
            'value' => 'Dholers Inside-June-copy (v3)',
        ]);

        Livewire::test(LeadDetail::class)
            ->set('isOpen', true)
            ->set('leadId', $lead->id)
            ->assertSee('Meta Lead Submission')
            ->assertSee('Would You Like To Schedule A Site Visit To Dholera')
            ->assertSee('Yes, I Want A Site Visit')
            ->assertSee('When Are You Planning To Purchase')
            ->assertSee('Immediately (0–30 Days)')
            ->assertSee('Suitable Time For Call ☎️')
            ->assertSee('12 Noon To 4 PM')
            ->assertSee('Which Type Of Plot Are You Interested In At Dholera')
            ->assertSee('₹17.83 लाख से ₹69 लाख कुल निवेश।')
            ->assertSee('Meta Context Attribution')
            ->assertSee('SIR Inside Plots 1- Palacio')
            ->assertSee('SIR Inside Plots Creative A')
            ->assertSee('Dholers Inside-June-copy (v3)')
            // Contact fields shouldn't be duplicated in the Q&A list
            ->assertDontSee('Full Name: Harsh Kant Saxena')
            ->assertDontSee('Phone Number: +918003476637');
    }

    public function test_renders_fallback_meta_requirement_with_na_for_missing_context(): void
    {
        $req = "Meta Lead Submission:\n• Unit Preference: 3 BHK Luxury\n• Visit Date: Next Weekend\n• Meta Context: Campaign: Sky Towers";

        $lead = Lead::factory()->create([
            'lead_source_id' => $this->metaSource->id,
            'requirement' => $req,
        ]);

        Livewire::test(LeadDetail::class)
            ->set('isOpen', true)
            ->set('leadId', $lead->id)
            ->assertSee('Meta Lead Submission')
            ->assertSee('Unit Preference')
            ->assertSee('3 BHK Luxury')
            ->assertSee('Visit Date')
            ->assertSee('Next Weekend')
            ->assertSee('Sky Towers')
            ->assertSee('N/A');
    }

    public function test_renders_standard_requirement_for_non_meta_leads(): void
    {
        $directSource = LeadSource::firstOrCreate(['name' => 'Direct']);

        $lead = Lead::factory()->create([
            'lead_source_id' => $directSource->id,
            'requirement' => 'Client is interested in ready-to-move commercial office spaces.',
        ]);

        Livewire::test(LeadDetail::class)
            ->set('isOpen', true)
            ->set('leadId', $lead->id)
            ->assertDontSee('Meta Lead Submission')
            ->assertDontSee('Meta Context Attribution')
            ->assertSee('Customer Requirement / Inquiry:')
            ->assertSee('Client is interested in ready-to-move commercial office spaces.');
    }
}
