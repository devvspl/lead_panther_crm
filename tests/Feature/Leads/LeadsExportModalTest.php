<?php

namespace Tests\Feature\Leads;

use App\Exports\BaseStyledExport;
use App\Livewire\Leads\ClientLeadManager;
use App\Livewire\Leads\LeadKanban;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\LeadMetadata;
use App\Models\LeadSource;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class LeadsExportModalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Organization $org;
    private User $builderOwner;
    private Project $project;
    private Campaign $campaign;
    private LeadSource $source;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');
        $this->actingAs($this->admin);

        $this->org = Organization::create([
            'name' => 'Truegrowth Realty',
            'status' => 'active',
        ]);

        $this->builderOwner = User::factory()->create([
            'organization_id' => $this->org->id,
            'name' => 'Truegrowth Builder',
        ]);
        $this->builderOwner->assignRole('Builder/Developer');

        $this->project = Project::create([
            'client_id' => $this->builderOwner->id,
            'name' => 'Studio Buy Leads',
            'status' => 'active',
        ]);

        $this->source = LeadSource::firstOrCreate(['name' => 'meta']);

        $this->campaign = Campaign::create([
            'project_id' => $this->project->id,
            'lead_source_id' => $this->source->id,
            'name' => 'Studio Noida Exp 50 – 2 gulmohar',
            'status' => 'active',
        ]);
    }

    public function test_clicking_export_to_excel_opens_modal_with_default_columns(): void
    {
        Livewire::test(LeadKanban::class)
            ->assertSet('showExportModal', false)
            ->call('exportExcel')
            ->assertSet('showExportModal', true)
            ->assertSee('Export Leads to Excel')
            ->assertSee('Lead Information')
            ->assertSee('Project & Campaign')
            ->assertSee('Assignment Details')
            ->assertSee('Lead Status & Follow-up')
            ->assertSee('Additional Details')
            ->assertSee('columns selected');
    }

    public function test_select_all_and_clear_all_behavior(): void
    {
        $component = Livewire::test(LeadKanban::class)
            ->call('openExportModal');

        // Initial defaults has 15 columns
        $this->assertCount(15, $component->get('selectedExportColumns'));

        // Select All -> all 28 columns
        $component->call('selectAllExportColumns');
        $this->assertCount(28, $component->get('selectedExportColumns'));

        // Clear All -> 0 columns
        $component->call('clearAllExportColumns');
        $this->assertCount(0, $component->get('selectedExportColumns'));

        // Reset to Default -> 15 columns
        $component->call('resetExportColumnsToDefault');
        $this->assertCount(15, $component->get('selectedExportColumns'));
    }

    public function test_toggle_category_checkbox_behavior(): void
    {
        $component = Livewire::test(LeadKanban::class)
            ->call('openExportModal')
            ->call('clearAllExportColumns');

        $this->assertCount(0, $component->get('selectedExportColumns'));

        // Toggle Lead Information (8 fields)
        $component->call('toggleExportCategory', 'lead_information');
        $this->assertCount(8, $component->get('selectedExportColumns'));
        $this->assertContains('lead_code', $component->get('selectedExportColumns'));
        $this->assertContains('mobile', $component->get('selectedExportColumns'));

        // Toggle again to deselect all in lead_information
        $component->call('toggleExportCategory', 'lead_information');
        $this->assertCount(0, $component->get('selectedExportColumns'));
    }

    public function test_confirm_export_requires_at_least_one_column(): void
    {
        Livewire::test(LeadKanban::class)
            ->call('openExportModal')
            ->call('clearAllExportColumns')
            ->call('confirmExport')
            ->assertSet('showExportModal', true)
            ->assertSet('exportValidationError', 'Please select at least one column to export.')
            ->assertSee('Please select at least one column to export.');
    }

    public function test_confirm_export_downloads_excel_with_selected_columns_and_data(): void
    {
        Excel::fake();

        // Create a test lead
        $lead = Lead::create([
            'lead_code' => 'LP-2026-00002368',
            'client_id' => $this->builderOwner->id,
            'campaign_id' => $this->campaign->id,
            'lead_source_id' => $this->source->id,
            'name' => 'Nitesh Pratap Singh',
            'mobile' => '917974044299',
            'email' => 'nitesh@example.com',
            'city' => 'Noida',
            'status' => 'new',
            'current_stage' => 'new',
        ]);
        LeadMetadata::create([
            'lead_id' => $lead->id,
            'key' => 'meta_form_answers',
            'value' => json_encode(['want_to_buy_studio' => 'buy']),
        ]);

        Livewire::test(LeadKanban::class)
            ->call('openExportModal')
            ->set('selectedExportColumns', ['lead_code', 'name', 'mobile', 'project_name', 'organization', 'custom_lead_fields'])
            ->call('confirmExport')
            ->assertSet('showExportModal', false)
            ->assertDispatched('toast', type: 'success');

        Excel::matchByRegex();
        Excel::assertDownloaded('/^leads-export_.*\.xlsx$/', function (BaseStyledExport $export) use ($lead) {
            $mapped = $export->map($lead);
            // Column 0: Lead Code
            // Column 1: Lead Name
            // Column 2: Mobile Number
            // Column 3: Project Name
            // Column 4: Organization / Client
            // Column 5: Custom Lead Fields
            return $mapped[0] === 'LP-2026-00002368'
                && $mapped[1] === 'Nitesh Pratap Singh'
                && $mapped[2] === '917974044299'
                && $mapped[3] === 'Studio Buy Leads'
                && $mapped[4] === 'Truegrowth Realty'
                && str_contains($mapped[5], 'want_to_buy_studio: buy');
        });
    }

    public function test_export_modal_works_inside_client_lead_manager(): void
    {
        Livewire::test(ClientLeadManager::class, ['organizationId' => $this->org->id])
            ->assertSet('showExportModal', false)
            ->call('exportExcel')
            ->assertSet('showExportModal', true)
            ->assertSee('Export Leads to Excel');
    }
}
