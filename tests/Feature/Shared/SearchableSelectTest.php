<?php

namespace Tests\Feature\Shared;

use Tests\TestCase;
use App\Models\Project;
use App\Models\Client;
use App\Models\User;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Livewire\Shared\SearchableSelect;

class SearchableSelectTest extends TestCase
{
    use RefreshDatabase;

    public function test_searchable_select_renders_initial_results_and_supports_search(): void
    {
        $org = \App\Models\Organization::create(['name' => 'Test Org', 'type' => 'builder']);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Test Client']);

        for ($i = 1; $i <= 25; $i++) {
            Project::create([
                'client_id' => $client->id,
                'name' => "Project Alpha {$i}",
            ]);
        }

        Project::create([
            'client_id' => $client->id,
            'name' => 'Project Zeta Special',
        ]);

        Livewire::test(SearchableSelect::class, [
            'model' => Project::class,
            'placeholder' => 'All Projects',
        ])
        ->assertSee('Project Alpha 1')
        ->assertSee('Load more (+20)...')
        ->set('search', 'Zeta')
        ->assertSee('Project Zeta Special')
        ->assertDontSee('Project Alpha 1')
        ->call('selectOption', Project::where('name', 'Project Zeta Special')->first()->id)
        ->assertSet('value', Project::where('name', 'Project Zeta Special')->first()->id)
        ->call('clearSelection')
        ->assertSet('value', null)
        ->assertSet('search', '');
    }

    public function test_searchable_select_load_more_appends_results(): void
    {
        $org = \App\Models\Organization::create(['name' => 'Test Org 2', 'type' => 'builder']);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Test Client']);

        for ($i = 1; $i <= 30; $i++) {
            Project::create([
                'client_id' => $client->id,
                'name' => sprintf('Project %02d', $i),
            ]);
        }

        Livewire::test(SearchableSelect::class, [
            'model' => Project::class,
            'placeholder' => 'All Projects',
        ])
        ->assertSet('page', 1)
        ->call('loadMore')
        ->assertSet('page', 2)
        ->assertSee('Project 25');
    }
}
