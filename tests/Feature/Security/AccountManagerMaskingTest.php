<?php

namespace Tests\Feature\Security;

use Tests\TestCase;
use App\Models\User;
use App\Models\Organization;
use App\Models\Client;
use App\Models\Project;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Support\LeadPresenter;
use App\Livewire\AccountManager\AccountManagerLeads;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

class AccountManagerMaskingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'Super Admin']);
        Role::create(['name' => 'Account Manager']);
        Role::create(['name' => 'account-manager']);
    }

    public function test_account_manager_api_response_masks_mobile_and_email(): void
    {
        $am = User::factory()->create(['name' => 'Account Manager User']);
        $am->assignRole('Account Manager');

        $org = Organization::create(['name' => 'Org Sec', 'type' => 'builder']);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Client Sec']);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Project Sec']);
        $source = LeadSource::create(['name' => 'meta']);

        $rawMobile = '9811223344';
        $rawEmail = 'vikram.sethi@example.com';

        $lead = Lead::factory()->create([
            'project_id' => $project->id,
            'client_id' => $client->id,
            'lead_source_id' => $source->id,
            'mobile' => $rawMobile,
            'email' => $rawEmail,
        ]);

        $response = $this->actingAs($am)->getJson("/api/leads/{$lead->id}");
        $response->assertOk();

        // Assert unmasked raw PII strings do NOT appear in response payload
        $content = $response->getContent();
        $this->assertStringNotContainsString($rawMobile, $content);
        $this->assertStringNotContainsString($rawEmail, $content);

        // Assert masked strings are present
        $maskedMobile = LeadPresenter::maskMobile($rawMobile);
        $maskedEmail = LeadPresenter::maskEmail($rawEmail);
        $response->assertJsonPath('data.mobile', $maskedMobile);
        $response->assertJsonPath('data.email', $maskedEmail);
    }

    public function test_super_admin_api_response_returns_unmasked_pii(): void
    {
        $admin = User::factory()->create(['name' => 'Admin User']);
        $admin->assignRole('Super Admin');

        $org = Organization::create(['name' => 'Org Sec 2', 'type' => 'builder']);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Client Sec 2']);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Project Sec 2']);
        $source = LeadSource::create(['name' => 'google']);

        $rawMobile = '9822334455';
        $rawEmail = 'anita.roy@example.com';

        $lead = Lead::factory()->create([
            'project_id' => $project->id,
            'client_id' => $client->id,
            'lead_source_id' => $source->id,
            'mobile' => $rawMobile,
            'email' => $rawEmail,
        ]);

        $response = $this->actingAs($admin)->getJson("/api/leads/{$lead->id}");
        $response->assertOk();

        $response->assertJsonPath('data.mobile', $rawMobile);
        $response->assertJsonPath('data.email', $rawEmail);
    }

    public function test_account_manager_dashboard_renders_masked_data(): void
    {
        $am = User::factory()->create();
        $am->assignRole('Account Manager');

        $org = Organization::create(['name' => 'Org Sec 3', 'type' => 'builder']);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Client Sec 3']);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Project Sec 3']);
        $source = LeadSource::create(['name' => 'portal']);

        $rawMobile = '9833445566';
        $rawEmail = 'karan.johor@example.com';

        $lead = Lead::factory()->create([
            'project_id' => $project->id,
            'client_id' => $client->id,
            'lead_source_id' => $source->id,
            'mobile' => $rawMobile,
            'email' => $rawEmail,
        ]);

        $response = $this->actingAs($am)->get('/account-manager/leads');
        $response->assertOk();

        Livewire::actingAs($am)
            ->test(AccountManagerLeads::class)
            ->assertSee(LeadPresenter::maskMobile($rawMobile))
            ->assertDontSee($rawMobile);
    }
}
