<?php

namespace Tests\Feature\Communication;

use Tests\TestCase;
use App\Models\User;
use App\Models\Organization;
use App\Models\Client;
use App\Models\Project;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\Followup;
use App\Models\NotificationTemplate;
use App\Models\NotificationLog;
use App\Notifications\LeadAssignedNotification;
use App\Livewire\Shared\NotificationBell;
use App\Livewire\Admin\NotificationTemplates;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

class CommunicationEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'Super Admin']);
        Role::create(['name' => 'sales-executive']);
    }

    public function test_lead_assigned_notification_dispatches_and_logs_delivery(): void
    {
        $user = User::factory()->create(['phone' => '9876543210']);
        $user->assignRole('sales-executive');

        $org = Organization::create(['name' => 'Builder Org', 'type' => 'builder']);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Builder Client']);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Sky Towers']);
        $source = LeadSource::create(['name' => 'meta']);

        $lead = Lead::factory()->create(['project_id' => $project->id, 'client_id' => $client->id, 'lead_source_id' => $source->id]);

        $user->notify(new LeadAssignedNotification($lead));

        // Check database notification
        $this->assertEquals(1, $user->unreadNotifications->count());

        // Check notification_logs
        $this->assertDatabaseHas('notification_logs', [
            'notifiable_id' => $user->id,
            'channel' => 'whatsapp',
            'status' => 'sent',
        ]);

        $this->assertDatabaseHas('notification_logs', [
            'notifiable_id' => $user->id,
            'channel' => 'sms',
            'status' => 'sent',
        ]);
    }

    public function test_notification_bell_component_renders_and_marks_all_as_read(): void
    {
        $user = User::factory()->create();
        $user->assignRole('sales-executive');

        $org = Organization::create(['name' => 'Builder Org 2', 'type' => 'builder']);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Builder Client 2']);
        $project = Project::create(['client_id' => $client->id, 'name' => 'River Palms']);
        $source = LeadSource::create(['name' => 'google']);

        $lead = Lead::factory()->create(['project_id' => $project->id, 'client_id' => $client->id, 'lead_source_id' => $source->id]);

        $user->notify(new LeadAssignedNotification($lead));
        $this->assertEquals(1, $user->fresh()->unreadNotifications->count());

        Livewire::actingAs($user)
            ->test(NotificationBell::class)
            ->call('markAllAsRead');

        $this->assertEquals(0, $user->fresh()->unreadNotifications->count());
    }

    public function test_notification_template_admin_screen_saves_and_renders(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $response = $this->actingAs($admin)->get('/admin/notification-templates');
        $response->assertOk();

        Livewire::actingAs($admin)
            ->test(NotificationTemplates::class)
            ->set('key', 'lead_assigned_whatsapp')
            ->set('channel', 'whatsapp')
            ->set('body', 'Hello {{lead_name}}, lead code {{lead_code}} is ready.')
            ->call('saveTemplate');

        $tpl = NotificationTemplate::where('key', 'lead_assigned_whatsapp')->first();
        $this->assertNotNull($tpl);

        $rendered = $tpl->render(['lead_name' => 'Rahul Sharma', 'lead_code' => 'LP-9988']);
        $this->assertEquals('Hello Rahul Sharma, lead code LP-9988 is ready.', $rendered);
    }

    public function test_check_due_followups_command_sends_notifications(): void
    {
        $user = User::factory()->create();
        $user->assignRole('sales-executive');

        $org = Organization::create(['name' => 'Builder Org 3', 'type' => 'builder']);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Builder Client 3']);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Project Followup']);
        $source = LeadSource::create(['name' => 'portal']);

        $lead = Lead::factory()->create(['project_id' => $project->id, 'client_id' => $client->id, 'lead_source_id' => $source->id]);

        Followup::create([
            'lead_id' => $lead->id,
            'created_by' => $user->id,
            'due_at' => now()->subMinutes(10),
            'note' => 'Call lead regarding 3BHK pricing',
            'status' => 'pending',
        ]);

        $this->artisan('followups:check-due')
            ->assertExitCode(0);

        $this->assertEquals(1, $user->fresh()->unreadNotifications->count());
    }
}
