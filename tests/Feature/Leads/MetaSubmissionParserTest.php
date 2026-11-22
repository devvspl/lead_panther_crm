<?php

namespace Tests\Feature\Leads;

use App\Models\Lead;
use App\Models\LeadMetadata;
use App\Models\LeadSource;
use App\Support\MetaSubmissionParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetaSubmissionParserTest extends TestCase
{
    use RefreshDatabase;

    protected LeadSource $source;

    protected function setUp(): void
    {
        parent::setUp();
        $this->source = LeadSource::firstOrCreate(['name' => 'meta']);
    }

    public function test_parses_meta_form_answers_with_hindi_symbols_and_emojis(): void
    {
        $lead = Lead::factory()->create([
            'lead_source_id' => $this->source->id,
            'requirement' => 'Meta Lead Submission: • Test: Yes',
        ]);

        LeadMetadata::create([
            'lead_id' => $lead->id,
            'key' => 'meta_form_answers',
            'value' => json_encode([
                'would_you_like_to_schedule_a_site_visit_to_dholera?' => 'Yes, I Want A Site Visit',
                'full_name' => 'Harsh Kant Saxena',
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
            'value' => 'SIR Inside Plots',
        ]);
        LeadMetadata::create([
            'lead_id' => $lead->id,
            'key' => 'meta_form_name',
            'value' => 'Dholers Inside-June-copy (v3)',
        ]);

        $lead->refresh();
        $lead->load('metadata');

        $result = MetaSubmissionParser::parse($lead);

        $this->assertTrue($result['is_meta']);
        $this->assertTrue($result['has_submission_data']);
        $this->assertCount(3, $result['questions']);

        // Check questions
        $this->assertEquals('Would You Like To Schedule A Site Visit To Dholera', $result['questions'][0]['question']);
        $this->assertEquals('Yes, I Want A Site Visit', $result['questions'][0]['answer']);

        $this->assertEquals('Suitable Time For Call ☎️', $result['questions'][1]['question']);
        $this->assertEquals('12 Noon To 4 PM', $result['questions'][1]['answer']);

        $this->assertEquals('Which Type Of Plot Are You Interested In At Dholera', $result['questions'][2]['question']);
        $this->assertStringContainsString('₹17.83 लाख से ₹69 लाख कुल निवेश।', $result['questions'][2]['answer']);

        // Check context
        $this->assertEquals('SIR Inside Plots 1- Palacio', $result['context']['campaign']);
        $this->assertEquals('SIR Inside Plots', $result['context']['adset']);
        $this->assertEquals('SIR Inside Plots', $result['context']['ad']);
        $this->assertEquals('Dholers Inside-June-copy (v3)', $result['context']['form']);
    }

    public function test_parses_fallback_requirement_text_when_metadata_is_absent(): void
    {
        $req = "Meta Lead Submission:\n• Required Configuration: 3 BHK\n• Would You Like To Schedule A Site Visit: Yes, I Want A Site Visit\n• Budget Details: ₹1.5 Cr approx (₹15,000/sq.ft)\n• Meta Context: Campaign: Panchsheel Greens 2 | AdSet: Sector 10 | Form: #1144292554640400";

        $lead = Lead::factory()->create([
            'lead_source_id' => $this->source->id,
            'requirement' => $req,
        ]);

        $result = MetaSubmissionParser::parse($lead);

        $this->assertTrue($result['is_meta']);
        $this->assertTrue($result['has_submission_data']);
        $this->assertCount(3, $result['questions']);

        $this->assertEquals('Required Configuration', $result['questions'][0]['question']);
        $this->assertEquals('3 BHK', $result['questions'][0]['answer']);

        $this->assertEquals('Would You Like To Schedule A Site Visit', $result['questions'][1]['question']);
        $this->assertEquals('Yes, I Want A Site Visit', $result['questions'][1]['answer']);

        $this->assertEquals('Budget Details', $result['questions'][2]['question']);
        $this->assertEquals('₹1.5 Cr approx (₹15,000/sq.ft)', $result['questions'][2]['answer']);

        $this->assertEquals('Panchsheel Greens 2', $result['context']['campaign']);
        $this->assertEquals('Sector 10', $result['context']['adset']);
        $this->assertEquals('N/A', $result['context']['ad']);
        $this->assertEquals('#1144292554640400', $result['context']['form']);
    }

    public function test_defaults_missing_context_values_to_na(): void
    {
        $lead = Lead::factory()->create([
            'lead_source_id' => $this->source->id,
            'requirement' => "Meta Lead Submission:\n• Preferred Floor: 5th Floor",
        ]);

        $result = MetaSubmissionParser::parse($lead);

        $this->assertEquals('N/A', $result['context']['campaign']);
        $this->assertEquals('N/A', $result['context']['adset']);
        $this->assertEquals('N/A', $result['context']['ad']);
        $this->assertEquals('N/A', $result['context']['form']);
    }

    public function test_identifies_non_meta_leads_correctly(): void
    {
        $directSource = LeadSource::firstOrCreate(['name' => 'Direct']);

        $lead = Lead::factory()->create([
            'lead_source_id' => $directSource->id,
            'requirement' => 'Looking for 3BHK flat near Metro station with 2 car parking.',
        ]);

        $result = MetaSubmissionParser::parse($lead);

        $this->assertFalse($result['is_meta']);
        $this->assertFalse($result['has_submission_data']);
        $this->assertEmpty($result['questions']);
        $this->assertEquals('Looking for 3BHK flat near Metro station with 2 car parking.', $result['raw_requirement']);
    }

    public function test_supports_dynamic_questions_without_hardcoded_field_names(): void
    {
        $lead = Lead::factory()->create([
            'lead_source_id' => $this->source->id,
            'requirement' => 'Meta Lead Submission:',
        ]);

        LeadMetadata::create([
            'lead_id' => $lead->id,
            'key' => 'meta_form_answers',
            'value' => json_encode([
                'custom_industry_question_x?' => 'Healthcare & Pharmaceuticals',
                'what_is_your_target_move_in_date' => 'October 2027',
                'team_size_preference' => ['5-10 Members', 'Flexible'],
            ]),
        ]);

        $lead->refresh();
        $lead->load('metadata');

        $result = MetaSubmissionParser::parse($lead);

        $this->assertTrue($result['is_meta']);
        $this->assertCount(3, $result['questions']);

        $this->assertEquals('Custom Industry Question X', $result['questions'][0]['question']);
        $this->assertEquals('Healthcare & Pharmaceuticals', $result['questions'][0]['answer']);

        $this->assertEquals('What Is Your Target Move In Date', $result['questions'][1]['question']);
        $this->assertEquals('October 2027', $result['questions'][1]['answer']);

        $this->assertEquals('Team Size Preference', $result['questions'][2]['question']);
        $this->assertEquals('5-10 Members, Flexible', $result['questions'][2]['answer']);
    }

    public function test_parses_single_line_bulleted_requirement_without_newlines(): void
    {
        $rawSingleLine = "Meta Lead Submission: • Would You Like To Schedule A Site Visit To Dholera: Yes, I Want A Site Visit • When Are You Planning To Purchase: Immediately (0-30 Days) • Suitable Time For Call ☎: 12 Noon To 4 PM • Which Type Of Plot Are You Interested In At Dholera: 488 Sq. Yard Commercial Plot ₹moneyplant: 155-600 Sq. Yard × ₹11, 500 = ₹17.83 लाख से ₹69 लाख कुल निवेश l/sq. Yard Total Investment: ₹19.5 Lakh Approx. • Meta Context: Campaign: SIR Inside Plots 1- Palacio | AdSet: SIR Inside Plots | Ad: SIR Inside Plots | Form: #3198112120381812";

        $lead = Lead::factory()->create([
            'lead_source_id' => $this->source->id,
            'requirement' => $rawSingleLine,
        ]);

        $result = MetaSubmissionParser::parse($lead);

        $this->assertTrue($result['is_meta']);
        $this->assertTrue($result['has_submission_data']);
        $this->assertCount(4, $result['questions']);

        $this->assertEquals('Would You Like To Schedule A Site Visit To Dholera', $result['questions'][0]['question']);
        $this->assertEquals('Yes, I Want A Site Visit', $result['questions'][0]['answer']);

        $this->assertEquals('When Are You Planning To Purchase', $result['questions'][1]['question']);
        $this->assertEquals('Immediately (0-30 Days)', $result['questions'][1]['answer']);

        $this->assertEquals('Suitable Time For Call ☎', $result['questions'][2]['question']);
        $this->assertEquals('12 Noon To 4 PM', $result['questions'][2]['answer']);

        $this->assertEquals('Which Type Of Plot Are You Interested In At Dholera', $result['questions'][3]['question']);
        $this->assertStringContainsString('₹17.83 लाख से ₹69 लाख कुल निवेश', $result['questions'][3]['answer']);

        $this->assertEquals('SIR Inside Plots 1- Palacio', $result['context']['campaign']);
        $this->assertEquals('SIR Inside Plots', $result['context']['adset']);
        $this->assertEquals('SIR Inside Plots', $result['context']['ad']);
        $this->assertEquals('#3198112120381812', $result['context']['form']);
        $this->assertEquals('3198112120381812', $result['context']['form_id']);
    }
}
