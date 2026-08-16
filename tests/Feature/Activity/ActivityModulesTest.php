<?php

namespace Tests\Feature\Activity;

use Tests\TestCase;
use App\Models\User;
use App\Models\Organization;
use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectUnit;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\Followup;
use App\Models\Meeting;
use App\Models\SiteVisit;
use App\Models\Proposal;
use App\Models\Booking;
use App\Models\PaymentReceived;
use App\Livewire\Activity\FollowupList;
use App\Livewire\Activity\MeetingForm;
use App\Livewire\Activity\SiteVisitForm;
use App\Livewire\Activity\ProposalForm;
use App\Livewire\Activity\BookingForm;
use App\Livewire\Activity\PaymentForm;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;

class ActivityModulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'Super Admin']);
        Role::create(['name' => 'sales-executive']);
    }

    public function test_followup_list_marks_done_and_creates_next_step(): void
    {
        $user = User::factory()->create();
        $user->assignRole('sales-executive');

        $org = Organization::create(['name' => 'Org 1', 'type' => 'builder']);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Client 1']);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Project 1']);
        $source = LeadSource::create(['name' => 'meta']);

        $lead = Lead::factory()->create(['project_id' => $project->id, 'client_id' => $client->id, 'lead_source_id' => $source->id]);
        $followup = Followup::create([
            'lead_id' => $lead->id,
            'created_by' => $user->id,
            'due_at' => now()->subHour(),
            'note' => 'Initial Call',
            'status' => 'pending',
        ]);

        Livewire::actingAs($user)
            ->test(FollowupList::class, ['lead' => $lead])
            ->call('markDone', $followup->id)
            ->set('nextDueAt', now()->addDays(2)->format('Y-m-d\TH:i'))
            ->set('nextNote', 'Second Call Scheduled')
            ->call('completeWithNextStep');

        $this->assertEquals('completed', $followup->fresh()->status);
        $this->assertDatabaseHas('followups', [
            'lead_id' => $lead->id,
            'note' => 'Second Call Scheduled',
            'status' => 'pending',
        ]);
    }

    public function test_meeting_form_schedules_meeting_and_updates_stage(): void
    {
        $user = User::factory()->create();
        $user->assignRole('sales-executive');

        $org = Organization::create(['name' => 'Org 2', 'type' => 'builder']);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Client 2']);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Project 2']);
        $source = LeadSource::create(['name' => 'google']);

        $lead = Lead::factory()->create(['project_id' => $project->id, 'client_id' => $client->id, 'lead_source_id' => $source->id]);

        Livewire::actingAs($user)
            ->test(MeetingForm::class, ['lead' => $lead])
            ->set('scheduledAt', now()->addDay()->format('Y-m-d\TH:i'))
            ->set('location', 'Site Office Bandra')
            ->set('notes', 'Review master plan layout')
            ->call('scheduleMeeting');

        $this->assertDatabaseHas('meetings', [
            'lead_id' => $lead->id,
            'location' => 'Site Office Bandra',
        ]);

        $this->assertEquals('Meeting', $lead->fresh()->current_stage);
    }

    public function test_site_visit_form_logs_visit_and_confirms_stage_update(): void
    {
        $user = User::factory()->create();
        $user->assignRole('sales-executive');

        $org = Organization::create(['name' => 'Org 3', 'type' => 'builder']);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Client 3']);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Project 3']);
        $unit = ProjectUnit::create(['project_id' => $project->id, 'unit_number' => '101A']);
        $source = LeadSource::create(['name' => 'portal']);

        $lead = Lead::factory()->create(['project_id' => $project->id, 'client_id' => $client->id, 'lead_source_id' => $source->id]);

        Livewire::actingAs($user)
            ->test(SiteVisitForm::class, ['lead' => $lead])
            ->set('projectUnitId', $unit->id)
            ->set('visitedAt', now()->format('Y-m-d\TH:i'))
            ->set('outcome', 'interested')
            ->call('logVisit')
            ->call('confirmUpdateStage');

        $this->assertDatabaseHas('site_visits', [
            'lead_id' => $lead->id,
            'outcome' => 'interested',
        ]);

        $this->assertEquals('Site Visit', $lead->fresh()->current_stage);
    }

    public function test_proposal_form_creates_proposal_and_signed_viewer_tracks_viewed_at(): void
    {
        $user = User::factory()->create();
        $user->assignRole('sales-executive');

        $org = Organization::create(['name' => 'Org 4', 'type' => 'builder']);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Client 4']);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Project 4']);
        $unit = ProjectUnit::create(['project_id' => $project->id, 'unit_number' => '202B']);
        $source = LeadSource::create(['name' => 'owned']);

        $lead = Lead::factory()->create(['project_id' => $project->id, 'client_id' => $client->id, 'lead_source_id' => $source->id]);

        Livewire::actingAs($user)
            ->test(ProposalForm::class, ['lead' => $lead])
            ->set('projectUnitId', $unit->id)
            ->set('price', 8000000.00)
            ->set('discount', 100000.00)
            ->call('createProposal');

        $proposal = Proposal::where('lead_id', $lead->id)->first();
        $this->assertNotNull($proposal);

        // Generate signed URL
        $signedUrl = URL::temporarySignedRoute(
            'proposals.public_view',
            now()->addHour(),
            ['proposal' => $proposal->id]
        );

        $response = $this->get($signedUrl);
        $response->assertOk();

        $this->assertNotNull($proposal->fresh()->viewed_at);
    }

    public function test_booking_form_locks_unit_and_prevents_double_booking(): void
    {
        $user = User::factory()->create();
        $user->assignRole('sales-executive');

        $org = Organization::create(['name' => 'Org 5', 'type' => 'builder']);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Client 5']);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Project 5']);
        $unit = ProjectUnit::create(['project_id' => $project->id, 'unit_number' => '303C', 'status' => 'available']);
        $source = LeadSource::create(['name' => 'meta']);

        $lead1 = Lead::factory()->create(['project_id' => $project->id, 'client_id' => $client->id, 'lead_source_id' => $source->id]);
        $lead2 = Lead::factory()->create(['project_id' => $project->id, 'client_id' => $client->id, 'lead_source_id' => $source->id]);

        // First booking succeeds
        Livewire::actingAs($user)
            ->test(BookingForm::class, ['lead' => $lead1])
            ->set('projectUnitId', $unit->id)
            ->set('bookingAmount', 500000.00)
            ->call('convertToBooking');

        $this->assertEquals('reserved', strtolower($unit->fresh()->status));
        $this->assertEquals('Booking', $lead1->fresh()->current_stage);

        // Second booking attempt on same unit fails
        Livewire::actingAs($user)
            ->test(BookingForm::class, ['lead' => $lead2])
            ->set('projectUnitId', $unit->id)
            ->set('bookingAmount', 500000.00)
            ->call('convertToBooking')
            ->assertDispatched('toast', type: 'error');
    }

    public function test_payment_form_records_payment_and_transitions_to_closed_won(): void
    {
        $user = User::factory()->create();
        $user->assignRole('sales-executive');

        $org = Organization::create(['name' => 'Org 6', 'type' => 'builder']);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Client 6']);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Project 6']);
        $unit = ProjectUnit::create(['project_id' => $project->id, 'unit_number' => '404D', 'status' => 'reserved']);
        $source = LeadSource::create(['name' => 'google']);

        $lead = Lead::factory()->create(['project_id' => $project->id, 'client_id' => $client->id, 'lead_source_id' => $source->id]);
        $booking = Booking::create([
            'lead_id' => $lead->id,
            'project_unit_id' => $unit->id,
            'booking_amount' => 300000.00,
            'booked_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(PaymentForm::class, ['lead' => $lead])
            ->set('bookingId', $booking->id)
            ->set('amount', 300000.00)
            ->set('paymentMethod', 'bank_transfer')
            ->call('recordPayment');

        $this->assertEquals('Closed Won', $lead->fresh()->current_stage);
        $this->assertEquals('sold', strtolower($unit->fresh()->status));
    }
}
