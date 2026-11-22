<?php

namespace Tests\Feature\Leads;

use App\Livewire\Leads\BulkLeadUpload;
use App\Livewire\Leads\ClientLeadManager;
use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\Organization;
use App\Models\Project;
use App\Models\UploadBatch;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class ClientLeadImportModalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Organization $organization;
    private Client $client;
    private Project $project;
    private LeadSource $source;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->create(['status' => 'active']);
        $this->admin->assignRole('Super Admin');
        $this->actingAs($this->admin);

        $this->organization = Organization::create([
            'name' => 'Import Modal Org',
            'status' => 'active',
            'auto_assign_enabled' => false,
        ]);

        $this->client = Client::create([
            'name' => 'Import Client Owner',
            'organization_id' => $this->organization->id,
            'status' => 'active',
        ]);
        User::findOrFail($this->client->id)->assignRole('Builder/Developer');

        $this->project = Project::create([
            'client_id' => $this->client->id,
            'name' => 'Import Modal Project',
            'status' => 'active',
        ]);

        $this->source = LeadSource::create(['name' => 'Import Source']);
    }

    public function test_client_lead_manager_renders_import_button_and_modal_is_initially_closed(): void
    {
        Livewire::test(ClientLeadManager::class, ['organizationId' => $this->organization->id])
            ->assertSee('Import Excel / CSV')
            ->assertSet('showClientImport', false)
            ->assertSee('client-lead-import-modal');
    }

    public function test_open_and_close_client_import_modal(): void
    {
        $manager = Livewire::test(ClientLeadManager::class, ['organizationId' => $this->organization->id])
            ->assertSet('showClientImport', false)
            ->call('openClientImport')
            ->assertSet('showClientImport', true)
            ->call('closeClientImport')
            ->assertSet('showClientImport', false);

        // Also test dispatching event
        $manager->call('openClientImport')
            ->assertSet('showClientImport', true)
            ->dispatch('close-client-import')
            ->assertSet('showClientImport', false);
    }

    public function test_bulk_lead_upload_inside_client_modal_runs_complete_workflow_and_summary(): void
    {
        // Existing lead to test duplicate detection
        Lead::create([
            'client_id' => $this->client->id,
            'project_id' => $this->project->id,
            'lead_source_id' => $this->source->id,
            'lead_code' => 'LP-MODAL-0001',
            'name' => 'Existing Contact',
            'mobile' => '9876543210',
            'status' => 'new',
            'current_stage' => 'new',
        ]);

        $csv = "Full Name,Mobile Number,Email,City\n" .
               "New Contact One,9111222333,contact1@test.com,Ahmedabad\n" .
               "Existing Contact,9876543210,existing@test.com,Surat\n" .
               "Bad Mobile,invalid,bad@test.com,Vadodara\n";

        $file = UploadedFile::fake()->createWithContent('modal_import.csv', $csv);

        // Step 1: Upload & Mapping inside client modal context
        $uploader = Livewire::test(BulkLeadUpload::class, ['organizationId' => $this->organization->id])
            ->set('file', $file)
            ->assertSee('modal_import.csv')
            ->assertSee('Previewing First 10 Rows')
            ->set('columnMapping', [
                'name' => '0',
                'mobile' => '1',
                'email' => '2',
                'city' => '3',
            ])
            ->call('proceedToAttribution')
            ->assertSet('step', 2);

        // Step 2: Attribution
        $uploader->set('projectId', $this->project->id)
            ->call('processBatch')
            ->assertSet('step', 3)
            ->assertDispatched('lead-updated')
            ->assertDispatched('bulk-import-completed');

        // Verify summary workflow
        $this->assertDatabaseHas('upload_batches', [
            'project_id' => $this->project->id,
            'filename' => 'modal_import.csv',
            'total_rows' => 3,
            'imported_count' => 1,
            'skipped_count' => 1,
            'failed_count' => 1,
        ]);

        $batch = UploadBatch::latest()->first();
        $this->assertNotNull($batch);

        // Step 3 UI renders the summary and the Close & View Leads button
        $uploader->assertSee('Import Completed Successfully')
            ->assertSee('Close & View Leads')
            ->assertSee('Download Error CSV');

        // Calling finishAndClose dispatches close-client-import
        $uploader->call('finishAndClose')
            ->assertDispatched('close-client-import')
            ->assertDispatched('lead-updated');

        // Check that new lead exists in DB with client scope
        $this->assertDatabaseHas('leads', [
            'name' => 'New Contact One',
            'mobile' => '+919111222333',
            'client_id' => $this->client->id,
        ]);
    }

    public function test_successful_import_refreshes_client_lead_manager_table_and_kpis(): void
    {
        $manager = Livewire::test(ClientLeadManager::class, ['organizationId' => $this->organization->id]);
        $initialTotal = $manager->instance()->builderLeadKpis['all']['value'];

        // Perform import
        $csv = "Name,Mobile\nFresh Lead,9988776655\n";
        $file = UploadedFile::fake()->createWithContent('fresh.csv', $csv);

        Livewire::test(BulkLeadUpload::class, ['organizationId' => $this->organization->id])
            ->set('file', $file)
            ->set('columnMapping', ['name' => '0', 'mobile' => '1'])
            ->call('proceedToAttribution')
            ->set('projectId', $this->project->id)
            ->call('processBatch')
            ->call('finishAndClose');

        // Manager receives event and refreshes
        $manager->dispatch('close-client-import')
            ->assertSet('showClientImport', false)
            ->assertSee('Fresh Lead')
            ->assertSee('9988776655');

        $refreshedTotal = $manager->instance()->builderLeadKpis['all']['value'];
        $this->assertSame($initialTotal + 1, $refreshedTotal);
    }
}
