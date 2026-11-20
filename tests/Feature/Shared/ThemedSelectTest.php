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

    public function test_direct_table_view_rendering_does_not_require_a_slot(): void
    {
        foreach (['formCampaignMap.123' => ['7' => 'Campaign One'], 'formClientMap.123' => ['8' => 'Client One'], 'empty' => []] as $model => $options) {
            $html = view('components.ui.themed-select', [
                'attributes' => new \Illuminate\View\ComponentAttributeBag(['wire:model.live' => $model]),
                'options' => $options, 'searchable' => true,
            ])->render();
            $this->assertStringContainsString('leadPantherSelect', $html);
            foreach ($options as $label) $this->assertStringContainsString($label, $html);
        }
    }

    public function test_component_tags_support_options_and_html_slots(): void
    {
        $optionsHtml = \Illuminate\Support\Facades\Blade::render('<x-ui.themed-select :options="[1 => \'Option One\']" />');
        $slotHtml = \Illuminate\Support\Facades\Blade::render('<x-ui.themed-select><option value="2">Slot Option</option></x-ui.themed-select>');
        $this->assertStringContainsString('Option One', $optionsHtml);
        $this->assertStringContainsString('<option value="2">Slot Option</option>', $slotHtml);
    }

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
