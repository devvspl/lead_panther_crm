<?php

namespace Tests\Feature\Search;

use Tests\TestCase;
use App\Models\User;
use App\Models\Organization;
use App\Models\Client;
use App\Models\Project;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Livewire\Shared\GlobalSearch;
use App\Support\LeadPresenter;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'Super Admin']);
        Role::create(['name' => 'Account Manager']);
    }

    public function test_global_search_returns_grouped_results(): void
    {
        $admin = User::factory()->create(['name' => 'Global Admin']);
        $admin->assignRole('Super Admin');

        $org = Organization::create(['name' => 'Apex Infra', 'type' => 'builder']);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Apex Client']);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Apex Towers']);
        $source = LeadSource::create(['name' => 'meta']);

        $lead = Lead::factory()->create([
            'project_id' => $project->id,
            'client_id' => $client->id,
            'lead_source_id' => $source->id,
            'name' => 'Apex Customer',
            'mobile' => '9811223344',
        ]);

        Livewire::actingAs($admin)
            ->test(GlobalSearch::class)
            ->set('search', 'Apex')
            ->assertSee('Apex Customer')
            ->assertSee('Apex Towers')
            ->assertSee('Apex Client');
    }

    public function test_global_search_masks_pii_for_account_manager(): void
    {
        $am = User::factory()->create(['name' => 'Account Manager Searcher']);
        $am->assignRole('Account Manager');

        $org = Organization::create(['name' => 'Org Mask Search', 'type' => 'builder']);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Client Mask']);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Project Mask']);
        $source = LeadSource::create(['name' => 'google']);

        $rawMobile = '9877665544';

        $lead = Lead::factory()->create([
            'project_id' => $project->id,
            'client_id' => $client->id,
            'lead_source_id' => $source->id,
            'name' => 'Sensitive Lead',
            'mobile' => $rawMobile,
        ]);

        $maskedMobile = LeadPresenter::maskMobile($rawMobile);

        Livewire::actingAs($am)
            ->test(GlobalSearch::class)
            ->set('search', 'Sensitive')
            ->assertSee($maskedMobile)
            ->assertDontSee($rawMobile);
    }
}
