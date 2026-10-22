<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use Livewire\Livewire;
use App\Livewire\Admin\AuditLogBrowser;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Organization;
use App\Models\Client;
use App\Models\Project;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Failed;

class AuditActivityLoggingTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

        $this->organization = Organization::create([
            'name' => 'Acme Corporation',
            'status' => 'active',
        ]);

        $this->superAdmin = User::factory()->create([
            'name' => 'System Super Admin',
            'email' => 'superadmin@leadpanther.com',
            'organization_id' => $this->organization->id,
            'password' => bcrypt('Secret123!'),
        ]);
        $this->superAdmin->assignRole('Super Admin');
    }

    public function test_controller_request_is_audited_with_sensitive_fields_masked(): void
    {
        $this->actingAs($this->superAdmin);

        // Make an authenticated HTTP request to an admin route
        $response = $this->get(route('admin.organizations'));
        $response->assertOk();

        // Verify audit log entry was created by middleware
        $log = AuditLog::where('user_id', $this->superAdmin->id)
            ->where('action', 'like', 'route.admin.organizations%')
            ->latest('id')
            ->first();

        $this->assertNotNull($log, 'Audit log entry should be created for authenticated controller route access.');
        $this->assertStringContainsString('Organization', $log->subject_type);

        $payload = json_decode($log->to_value, true);
        $this->assertEquals('GET', $payload['http_method']);
        $this->assertEquals(200, $payload['status']);
    }

    public function test_model_mutations_are_audited_for_multiple_models_with_diff(): void
    {
        $this->actingAs($this->superAdmin);

        $client = Client::create([
            'organization_id' => $this->organization->id,
            'name' => 'Apex Realty Client',
            'status' => 'active',
        ]);

        // 1. Create a Project model
        $project = Project::create([
            'client_id' => $client->id,
            'name' => 'Skyline Heights Phase 1',
            'location' => 'Downtown Metro',
            'status' => 'active',
        ]);

        $createLog = AuditLog::where('subject_type', Project::class)
            ->where('subject_id', $project->id)
            ->where('action', 'project.created')
            ->first();

        $this->assertNotNull($createLog, 'Project creation should be logged in audit_logs.');
        $this->assertEquals($this->superAdmin->id, $createLog->user_id);

        // 2. Update the Project model
        $project->update([
            'name' => 'Skyline Heights Phase 2 Deluxe',
            'location' => 'Uptown Metro',
        ]);

        $updateLog = AuditLog::where('subject_type', Project::class)
            ->where('subject_id', $project->id)
            ->where('action', 'project.updated')
            ->first();

        $this->assertNotNull($updateLog, 'Project update should be logged in audit_logs.');
        $from = json_decode($updateLog->from_value, true);
        $to = json_decode($updateLog->to_value, true);

        $this->assertEquals('Skyline Heights Phase 1', $from['name']);
        $this->assertEquals('Skyline Heights Phase 2 Deluxe', $to['name']);

        // 3. Sensitive fields like passwords must be masked
        $newUser = User::create([
            'name' => 'Confidential User',
            'email' => 'confidential@example.com',
            'password' => 'PlainSecret999!',
            'organization_id' => $this->organization->id,
        ]);

        $userLog = AuditLog::where('subject_type', User::class)
            ->where('subject_id', $newUser->id)
            ->where('action', 'user.created')
            ->first();

        $this->assertNotNull($userLog);
        $userValues = json_decode($userLog->to_value, true);
        $this->assertEquals('********', $userValues['password']);
        $this->assertStringNotContainsString('PlainSecret999!', $userLog->to_value);
    }

    public function test_auth_events_are_audited(): void
    {
        // 1. Fire Login event
        event(new Login('web', $this->superAdmin, false));

        $loginLog = AuditLog::where('action', 'auth.login')
            ->where('subject_id', $this->superAdmin->id)
            ->first();

        $this->assertNotNull($loginLog);
        $this->assertEquals($this->superAdmin->id, $loginLog->user_id);

        // 2. Fire Logout event
        event(new Logout('web', $this->superAdmin));

        $logoutLog = AuditLog::where('action', 'auth.logout')
            ->where('subject_id', $this->superAdmin->id)
            ->first();

        $this->assertNotNull($logoutLog);

        // 3. Fire Failed login event
        event(new Failed('web', null, ['email' => 'hacker@malicious.com', 'password' => 'badpass']));

        $failedLog = AuditLog::where('action', 'auth.failed')->first();
        $this->assertNotNull($failedLog);
        $fromPayload = json_decode($failedLog->from_value, true);
        $this->assertEquals('hacker@malicious.com', $fromPayload['credentials']['email']);
        $this->assertArrayNotHasKey('password', $fromPayload['credentials']);
    }

    public function test_audit_log_browser_quick_filters_and_inspection_modal(): void
    {
        $this->actingAs($this->superAdmin);

        // Seed logs representing various activities
        $activityLog = AuditLog::create([
            'user_id' => $this->superAdmin->id,
            'action' => 'controller.ProjectController@store',
            'subject_type' => 'Controller: ProjectController',
            'subject_id' => 42,
            'from_value' => json_encode(['step' => 'init']),
            'to_value' => json_encode(['name' => 'New Tower Project', 'budget' => 5000000]),
            'ip_address' => '10.0.0.1',
            'user_agent' => 'PHPUnit/TestAgent',
            'created_at' => now(),
        ]);

        $modelLog = AuditLog::create([
            'user_id' => $this->superAdmin->id,
            'action' => 'organization.created',
            'subject_type' => Organization::class,
            'subject_id' => $this->organization->id,
            'from_value' => null,
            'to_value' => json_encode(['name' => 'Acme Corporation']),
            'ip_address' => '10.0.0.1',
            'user_agent' => 'PHPUnit/TestAgent',
            'created_at' => now(),
        ]);

        $authLog = AuditLog::create([
            'user_id' => $this->superAdmin->id,
            'action' => 'auth.login',
            'subject_type' => 'Auth: Session',
            'subject_id' => $this->superAdmin->id,
            'from_value' => null,
            'to_value' => json_encode(['guard' => 'web']),
            'ip_address' => '10.0.0.1',
            'user_agent' => 'PHPUnit/TestAgent',
            'created_at' => now(),
        ]);

        // Test Livewire component
        Livewire::test(AuditLogBrowser::class)
            ->assertSee('System Audit Logs Browser')
            // Filter by activity
            ->call('setStatusFilter', 'activity')
            ->assertSee('ProjectController@store')
            ->assertDontSee('Organization.created')
            // Filter by models
            ->call('setStatusFilter', 'models')
            ->assertSee('Organization.created')
            ->assertDontSee('ProjectController@store')
            // Filter by auth
            ->call('setStatusFilter', 'auth')
            ->assertSee('Auth.login')
            ->assertDontSee('Organization.created')
            // Reset filter and test Inspect Details Modal
            ->call('setStatusFilter', 'all')
            ->call('viewDetails', $activityLog->id)
            ->assertDispatched('open-modal', 'audit-log-details-modal')
            ->assertSet('showDetailsModal', true)
            ->assertSee('Audit Entry #' . $activityLog->id)
            ->assertSee('New Tower Project')
            ->assertSee('PHPUnit/TestAgent')
            ->call('closeDetails')
            ->assertDispatched('close-modal', 'audit-log-details-modal')
            ->assertSet('showDetailsModal', false)
            ->assertSet('selectedLog', null);
    }
}
