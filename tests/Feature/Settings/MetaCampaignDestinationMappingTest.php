<?php

namespace Tests\Feature\Settings;

use App\Livewire\Settings\MetaAdAccounts;
use App\Models\{Campaign, Client, Lead, LeadFormMapping, MetaAdAccount, MetaAdRecord, Organization, PortalAccount, Project, User, WebhookLog};
use Spatie\Permission\Models\Role;
use App\Services\MetaCampaignLeadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Event, Http, Queue};
use Livewire\Livewire;
use Tests\TestCase;

class MetaCampaignDestinationMappingTest extends TestCase
{
    use RefreshDatabase;

    private function setupEnvironment(): array
    {
        Role::findOrCreate('Super Admin');
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('Super Admin');

        $orgA = Organization::create(['name' => 'Organization Alpha', 'status' => 'active']);
        $orgB = Organization::create(['name' => 'Organization Beta', 'status' => 'active']);

        $clientA = Client::create(['name' => 'Client Alpha', 'status' => 'active', 'organization_id' => $orgA->id]);
        $clientB = Client::create(['name' => 'Client Beta', 'status' => 'active', 'organization_id' => $orgB->id]);
        $clientNoProjects = Client::create(['name' => 'Client Without Projects', 'status' => 'active']);

        $projectA1 = Project::create(['client_id' => $clientA->id, 'name' => 'Alpha Tower 1']);
        $projectA2 = Project::create(['client_id' => $clientA->id, 'name' => 'Alpha Villas 2']);
        $projectB1 = Project::create(['client_id' => $clientB->id, 'name' => 'Beta Heights 1']);

        $page = PortalAccount::create(['name' => 'Main Page', 'type' => 'meta', 'status' => 'active']);
        $page->setCredential('page_id', 'page_123');
        $page->setCredential('access_token', 'page_token');

        $adAccount = MetaAdAccount::create([
            'portal_account_id' => $page->id,
            'name' => 'Lead Ad Account',
            'ad_account_id' => 'act_999999',
            'status' => 'connected',
            'access_token' => 'act_token',
        ]);
        $adAccount->pages()->attach($page);

        $campaignRecord = $adAccount->records()->create([
            'kind' => 'campaign',
            'remote_id' => 'camp_meta_1',
            'name' => 'Dholera Express Campaign',
            'ad_account_id' => 'act_999999',
            'status' => 'ACTIVE',
            'data' => [],
            'synced_at' => now(),
        ]);

        return compact('admin', 'orgA', 'orgB', 'clientA', 'clientB', 'clientNoProjects', 'projectA1', 'projectA2', 'projectB1', 'page', 'adAccount', 'campaignRecord');
    }

    public function test_crm_campaign_field_is_removed_and_project_is_required(): void
    {
        extract($this->setupEnvironment());

        $test = Livewire::actingAs($admin)
            ->test(MetaAdAccounts::class, ['integrationId' => $page->id])
            ->call('showRecords', $adAccount->id)
            ->call('openCampaignMapping', $campaignRecord->id)
            ->assertDontSee('CRM Campaign')
            ->assertSee('Client / Organization')
            ->assertSee('CRM Project');

        // Fails when project is missing
        $test->set('mappingClientId', (string) $clientA->id)
            ->set('mappingProjectId', '')
            ->call('saveCampaignMapping')
            ->assertHasErrors(['mappingProjectId']);
    }

    public function test_dynamic_project_loading_and_clearing_on_client_change(): void
    {
        extract($this->setupEnvironment());

        $test = Livewire::actingAs($admin)
            ->test(MetaAdAccounts::class, ['integrationId' => $page->id])
            ->call('showRecords', $adAccount->id)
            ->call('openCampaignMapping', $campaignRecord->id);

        // Select Client Alpha -> loads Alpha Tower 1 and Alpha Villas 2, not Beta Heights 1
        $test->set('mappingClientId', (string) $clientA->id);
        $projectsA = $test->viewData('mappingProjects');
        $this->assertArrayHasKey((string) $projectA1->id, $projectsA);
        $this->assertArrayHasKey((string) $projectA2->id, $projectsA);
        $this->assertArrayNotHasKey((string) $projectB1->id, $projectsA);

        // Select a project
        $test->set('mappingProjectId', (string) $projectA1->id);
        $this->assertSame((string) $projectA1->id, $test->get('mappingProjectId'));

        // Changing client clears previously selected project
        $test->set('mappingClientId', (string) $clientB->id);
        $this->assertSame('', $test->get('mappingProjectId'));
        $projectsB = $test->viewData('mappingProjects');
        $this->assertArrayHasKey((string) $projectB1->id, $projectsB);
        $this->assertArrayNotHasKey((string) $projectA1->id, $projectsB);
    }

    public function test_displays_no_projects_available_message_when_client_has_no_projects(): void
    {
        extract($this->setupEnvironment());

        Livewire::actingAs($admin)
            ->test(MetaAdAccounts::class, ['integrationId' => $page->id])
            ->call('showRecords', $adAccount->id)
            ->call('openCampaignMapping', $campaignRecord->id)
            ->set('mappingClientId', (string) $clientNoProjects->id)
            ->assertSee('No projects available for this organization.');
    }

    public function test_saves_only_client_id_and_project_id_and_validates_ownership(): void
    {
        extract($this->setupEnvironment());

        Livewire::actingAs($admin)
            ->test(MetaAdAccounts::class, ['integrationId' => $page->id])
            ->call('showRecords', $adAccount->id)
            ->call('openCampaignMapping', $campaignRecord->id)
            ->set('mappingClientId', (string) $clientA->id)
            ->set('mappingProjectId', (string) $projectA1->id)
            ->call('saveCampaignMapping')
            ->assertHasNoErrors();

        $campaignRecord->refresh();
        $this->assertSame((int) $clientA->id, (int) $campaignRecord->client_id);
        $this->assertSame((int) $projectA1->id, (int) $campaignRecord->project_id);
        $this->assertNull($campaignRecord->crm_campaign_id);

        // Validation rejects cross-client project
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(MetaCampaignLeadService::class)->saveMapping($campaignRecord, (int) $clientB->id, (int) $projectA1->id);
    }

    public function test_lead_routing_routes_to_mapped_client_and_project(): void
    {
        extract($this->setupEnvironment());

        // Map campaignRecord to Client A and Project A1
        app(MetaCampaignLeadService::class)->saveMapping($campaignRecord, (int) $clientA->id, (int) $projectA1->id);

        // Incoming webhook for this campaign
        Http::fake(['*' => Http::response([
            'id' => 'lead_meta_100',
            'created_time' => now()->toIso8601String(),
            'field_data' => [
                ['name' => 'full_name', 'values' => ['Vijay Verma']],
                ['name' => 'phone_number', 'values' => ['9876543210']],
                ['name' => 'email', 'values' => ['vijay@example.com']],
            ],
            'campaign_id' => 'camp_meta_1',
            'form_id' => 'form_100',
        ])]);

        $log = WebhookLog::create([
            'portal_account_id' => $page->id,
            'received_at' => now(),
            'payload' => json_encode([
                'entry' => [[
                    'id' => 'page_123',
                    'changes' => [[
                        'field' => 'leadgen',
                        'value' => [
                            'leadgen_id' => 'lead_meta_100',
                            'campaign_id' => 'camp_meta_1',
                            'form_id' => 'form_100',
                        ],
                    ]],
                ]],
            ]),
        ]);

        (new \App\Jobs\ProcessInboundLeadJob($log))->handle();

        $this->assertDatabaseHas('leads', [
            'client_id' => $clientA->id,
        ]);

        $lead = Lead::where('client_id', $clientA->id)->first();
        $this->assertNotNull($lead);
        $this->assertSame((int) $projectA1->id, (int) $lead->project_id);
        $this->assertSame((int) $projectA1->id, (int) $lead->campaign->project_id);
    }
}
