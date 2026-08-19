<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Organization;
use App\Models\Client;
use App\Models\Project;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\AuditLog;
use App\Livewire\Admin\AuditLogBrowser;
use App\Livewire\Admin\OrganizationManager;
use App\Livewire\Admin\UserManager;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

class SuperAdminSuiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'Super Admin']);
        Role::create(['name' => 'Sales Executive']);
    }

    public function test_audit_log_observer_captures_lead_mutations(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $org = Organization::create(['name' => 'Org Aud', 'type' => 'builder']);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Client Aud']);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Project Aud']);
        $source = LeadSource::create(['name' => 'meta']);

        $this->actingAs($admin);

        $lead = Lead::factory()->create([
            'project_id' => $project->id,
            'client_id' => $client->id,
            'lead_source_id' => $source->id,
            'current_stage' => 'New',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'lead.created',
            'subject_id' => $lead->id,
        ]);

        $lead->update(['current_stage' => 'Connected']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'lead.updated',
            'subject_id' => $lead->id,
        ]);
    }

    public function test_audit_log_browser_renders_and_filters(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'lead.created',
            'subject_type' => Lead::class,
            'subject_id' => 999,
            'created_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test(AuditLogBrowser::class)
            ->assertSee('Lead.created')
            ->set('actionType', 'lead')
            ->assertSee('Lead.created');
    }

    public function test_organization_manager_creates_and_toggles_status(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        Livewire::actingAs($admin)
            ->test(OrganizationManager::class)
            ->set('name', 'Apex Infra Developers')
            ->set('type', 'builder')
            ->set('status', 'active')
            ->call('createOrganization');

        $this->assertDatabaseHas('organizations', [
            'name' => 'Apex Infra Developers',
            'type' => 'builder',
            'status' => 'active',
        ]);

        $org = Organization::where('name', 'Apex Infra Developers')->first();

        Livewire::actingAs($admin)
            ->test(OrganizationManager::class)
            ->call('toggleStatus', $org->id);

        $this->assertEquals('suspended', $org->fresh()->status);
    }

    public function test_user_manager_updates_roles_and_impersonation_logs_audit_events(): void
    {
        $admin = User::factory()->create(['name' => 'Main Admin']);
        $admin->assignRole('Super Admin');

        $targetUser = User::factory()->create(['name' => 'Target Sales User']);

        // Update Role
        Livewire::actingAs($admin)
            ->test(UserManager::class)
            ->call('updateUserRole', $targetUser->id, 'Sales Executive');

        $this->assertTrue($targetUser->fresh()->hasRole('Sales Executive'));

        // Start Impersonation
        $response = $this->actingAs($admin)->get("/admin/users/{$targetUser->id}/impersonate");
        $response->assertRedirect('/dashboard');

        $this->assertEquals($targetUser->id, auth()->id());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.impersonate_start',
            'subject_id' => $targetUser->id,
        ]);

        // Stop Impersonation
        $stopResponse = $this->get('/impersonate/stop');
        $stopResponse->assertRedirect('/admin/users');

        $this->assertEquals($admin->id, auth()->id());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.impersonate_stop',
        ]);
    }

    public function test_user_manager_filters_by_role_and_search(): void
    {
        $admin = User::factory()->create(['name' => 'Main Admin', 'email' => 'admin@leadpanther.com']);
        $admin->assignRole('Super Admin');

        $salesUser = User::factory()->create(['name' => 'Amit Sales', 'email' => 'amit@sales.com']);
        $salesUser->assignRole('Sales Executive');

        $builderUser = User::factory()->create(['name' => 'Dev Builder', 'email' => 'dev@builder.com']);
        Role::firstOrCreate(['name' => 'Builder']);
        $builderUser->assignRole('Builder');

        // Test filtering by role
        Livewire::actingAs($admin)
            ->test(UserManager::class)
            ->set('roleFilter', 'Sales Executive')
            ->assertSee('Amit Sales')
            ->assertDontSee('Dev Builder')
            ->set('roleFilter', 'Builder')
            ->assertSee('Dev Builder')
            ->assertDontSee('Amit Sales')
            ->set('roleFilter', '')
            ->set('search', 'amit@sales.com')
            ->assertSee('Amit Sales')
            ->assertDontSee('Dev Builder');
    }

    public function test_organization_manager_creates_user_assigned_to_organization(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $org = Organization::create(['name' => 'Brigade Group', 'type' => 'builder', 'status' => 'active']);

        Livewire::actingAs($admin)
            ->test(OrganizationManager::class)
            ->call('openUserOffcanvas', $org->id)
            ->assertSet('selectedOrgId', $org->id)
            ->set('newUserName', 'Rohan Verma')
            ->set('newUserEmail', 'rohan@brigadegroup.com')
            ->set('newUserRole', 'Sales Executive')
            ->call('createUserForOrganization');

        $this->assertDatabaseHas('users', [
            'name' => 'Rohan Verma',
            'email' => 'rohan@brigadegroup.com',
            'organization_id' => $org->id,
        ]);

        $createdUser = User::where('email', 'rohan@brigadegroup.com')->first();
        $this->assertTrue($createdUser->hasRole('Sales Executive'));
    }

    public function test_user_manager_creates_user_with_parent_organization(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $org = Organization::create(['name' => 'Godrej Properties', 'type' => 'builder', 'status' => 'active']);

        Livewire::actingAs($admin)
            ->test(UserManager::class)
            ->set('newUserName', 'Sneha Patel')
            ->set('newUserEmail', 'sneha@godrej.com')
            ->set('newUserOrganizationId', $org->id)
            ->set('newUserRole', 'Sales Executive')
            ->call('createUser');

        $this->assertDatabaseHas('users', [
            'name' => 'Sneha Patel',
            'email' => 'sneha@godrej.com',
            'organization_id' => $org->id,
        ]);

        $createdUser = User::where('email', 'sneha@godrej.com')->first();
        $this->assertTrue($createdUser->hasRole('Sales Executive'));
    }
}
