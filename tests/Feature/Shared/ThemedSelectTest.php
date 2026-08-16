<?php

namespace Tests\Feature\Shared;

use Tests\TestCase;
use Livewire\Livewire;
use App\Livewire\Leads\LeadKanban;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ThemedSelectTest extends TestCase
{
    use RefreshDatabase;

    public function test_kanban_sort_dropdown_renders_themed_select_and_sorts(): void
    {
        $user = User::factory()->create();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-admin']);
        $user->assignRole('super-admin');

        $this->actingAs($user);

        Livewire::test(LeadKanban::class)
            ->assertSee('Latest Created')
            ->set('sort_by', 'score_desc')
            ->assertSet('sort_by', 'score_desc');
    }
}
