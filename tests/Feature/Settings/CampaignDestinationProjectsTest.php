<?php

namespace Tests\Feature\Settings;

use App\Livewire\Settings\MetaAdAccounts;
use App\Models\{Campaign, LeadFormMapping, LeadSource, MetaAdAccount, Organization, PortalAccount, Project, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Event};
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CampaignDestinationProjectsTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') throw new \LogicException('Isolated SQLite required.');
    }

    private function setupModal(): array
    {
        Event::fake();
        Role::findOrCreate('Super Admin');
        $admin = User::factory()->create(['status' => 'active']); $admin->assignRole('Super Admin');
        $org = Organization::create(['name' => 'Deltek', 'status' => 'active']);
        $owner = User::factory()->create(['name' => 'Deltek', 'organization_id' => $org->id, 'status' => 'active']);
        $member = User::factory()->create(['organization_id' => $org->id, 'status' => 'active']);
        $otherOrg = Organization::create(['name' => 'Another organization', 'status' => 'active']);
        $other = User::factory()->create(['organization_id' => $otherOrg->id, 'status' => 'active']);
        $project = Project::create(['name' => 'Deltek Project', 'client_id' => $owner->id]);
        $memberProject = Project::create(['name' => 'Deltek Member Project', 'client_id' => $member->id]);
        $otherProject = Project::create(['name' => 'Other Project', 'client_id' => $other->id]);
        $source = LeadSource::create(['name' => 'meta']);
        $campaign = Campaign::create(['name' => 'Existing CRM mapping', 'project_id' => $project->id, 'lead_source_id' => $source->id]);
        $page = PortalAccount::create(['name' => 'Page', 'type' => 'meta', 'status' => 'active']);
        $account = MetaAdAccount::create(['name' => 'Ads', 'ad_account_id' => '111', 'portal_account_id' => $page->id, 'status' => 'connected']);
        $record = $account->records()->create(['kind' => 'campaign', 'ad_account_id' => '111', 'remote_id' => 'campaign1', 'status' => 'ACTIVE', 'data' => [], 'synced_at' => now(), 'crm_campaign_id' => $campaign->id]);
        $component = Livewire::actingAs($admin)->test(MetaAdAccounts::class, ['integrationId' => $page->id])
            ->call('showRecords', $account->id)->call('openCampaignMapping', $record->id);
        return compact('component', 'org', 'owner', 'other', 'project', 'memberProject', 'otherProject', 'record', 'campaign', 'page');
    }

    public function test_deltek_projects_include_existing_organization_relationship_and_exclude_other_tenants(): void
    {
        extract($this->setupModal());
        $component->set('mappingClientId', (string) $owner->id)->assertSet('mappingClientValid', true)->assertSet('mappingProjectsFailed', false);
        $this->assertSame([$memberProject->id => $memberProject->name, $project->id => $project->name], $component->get('mappingProjectOptions'));
        $this->assertArrayNotHasKey($otherProject->id, $component->viewData('mappingProjects'));
        $this->assertNotSame($org->id, $owner->id, 'Fixture must distinguish organization ID from billing-user ID.');
    }

    public function test_switching_organization_clears_previous_project_and_replaces_options(): void
    {
        extract($this->setupModal());
        $component->set('mappingProjectId', (string) $project->id)->set('mappingClientId', (string) $other->id)
            ->assertSet('mappingProjectId', '')->assertSet('mappingCampaignId', '')
            ->assertSet('mappingProjectOptions', [$otherProject->id => $otherProject->name]);
        $this->assertArrayNotHasKey($project->id, $component->viewData('mappingProjects'));
    }

    public function test_empty_and_invalid_clients_disable_project_selection_and_show_clear_empty_state(): void
    {
        extract($this->setupModal());
        $empty = User::factory()->create(['status' => 'active']);
        $component->set('mappingClientId', (string) $empty->id)->assertSet('mappingProjectOptions', [])
            ->assertSee('No projects found for this organization.')
            ->set('mappingClientId', '')->assertSet('mappingClientValid', false)->assertSet('mappingProjectId', '')
            ->assertSee('Select client / organization first');
        $inactive = User::factory()->create(['status' => 'inactive']);
        $component->set('mappingClientId', (string) $inactive->id)->assertSet('mappingClientValid', false)->assertSet('mappingProjectOptions', []);
    }

    public function test_mapping_saves_selected_client_and_member_project_and_preserves_form_history(): void
    {
        extract($this->setupModal());
        $mapping = LeadFormMapping::create(['portal_account_id' => $page->id, 'form_id' => 'form1', 'form_name' => 'Existing form', 'client_id' => $other->id, 'project_id' => $otherProject->id]);
        $component->set('mappingClientId', (string) $owner->id)->set('mappingProjectId', (string) $memberProject->id)
            ->call('saveCampaignMapping')->assertHasNoErrors();
        $this->assertSame($owner->id, $record->fresh()->client_id);
        $this->assertSame($memberProject->id, $record->fresh()->project_id);
        $this->assertSame($otherProject->id, $mapping->fresh()->project_id);
        $this->assertSame($other->id, $mapping->fresh()->client_id);
    }

    public function test_forged_cross_organization_project_is_rejected_without_changing_existing_mapping(): void
    {
        extract($this->setupModal());
        $component->set('mappingClientId', (string) $owner->id)->set('mappingProjectId', (string) $otherProject->id)
            ->call('saveCampaignMapping')->assertHasErrors('mappingProjectId');
        $this->assertSame($campaign->id, $record->fresh()->crm_campaign_id);
    }

    public function test_reopen_preserves_historical_campaign_destination(): void
    {
        extract($this->setupModal());
        $component->assertSet('mappingClientId', (string) $owner->id)->assertSet('mappingProjectId', (string) $project->id);
        $this->assertArrayHasKey($project->id, $component->get('mappingProjectOptions'));
        $component->call('saveCampaignMapping')->assertHasNoErrors();
        $this->assertSame($campaign->id, $record->fresh()->crm_campaign_id);
    }

    public function test_project_load_failure_clears_options_and_retry_recovers(): void
    {
        extract($this->setupModal());
        $fail = true;
        DB::listen(function ($event) use (&$fail) {
            if ($fail && str_contains($event->sql, 'inner join "users"') && str_contains($event->sql, '"projects"."name"')) {
                throw new \RuntimeException('Simulated project query failure');
            }
        });
        $component->call('reloadMappingProjects')->assertSet('mappingProjectsFailed', true)->assertSet('mappingProjectOptions', [])->assertSee('Retry');
        $fail = false;
        $component->call('reloadMappingProjects')->assertSet('mappingProjectsFailed', false);
        $this->assertArrayHasKey($project->id, $component->get('mappingProjectOptions'));
    }
}
