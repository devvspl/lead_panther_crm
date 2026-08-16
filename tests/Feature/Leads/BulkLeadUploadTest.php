<?php

namespace Tests\Feature\Leads;

use Tests\TestCase;
use App\Models\User;
use App\Models\Organization;
use App\Models\Client;
use App\Models\Project;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\UploadBatch;
use App\Livewire\Leads\BulkLeadUpload;
use App\Livewire\Leads\UploadHistory;
use Livewire\Livewire;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

class BulkLeadUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'Super Admin']);
        Role::create(['name' => 'Sales Executive']);
    }

    public function test_bulk_lead_upload_view_and_history_render(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        Livewire::actingAs($admin)
            ->test(BulkLeadUpload::class)
            ->assertSee('Bulk Lead CSV / Excel Import');

        Livewire::actingAs($admin)
            ->test(UploadHistory::class)
            ->assertSee('Bulk Upload History');
    }

    public function test_csv_upload_processes_batch_and_skips_duplicates(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $org = Organization::create(['name' => 'Org Upload', 'type' => 'builder']);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Client Upload']);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Project Upload']);
        $source = LeadSource::create(['name' => 'csv_bulk']);

        // Existing Lead to trigger duplicate skip
        Lead::create([
            'lead_code' => 'LP-2026-00000001',
            'client_id' => $client->id,
            'project_id' => $project->id,
            'lead_source_id' => $source->id,
            'name' => 'Existing Person',
            'mobile' => '+919876543210',
            'status' => 'assigned',
            'current_stage' => 'assigned',
        ]);

        // Create temporary test CSV content
        $csvContent = "Full Name,Phone Number,Email Address,City\n" .
                      "New Person A,9123456789,new.a@example.com,Bengaluru\n" .
                      "Existing Person,9876543210,existing@example.com,Mumbai\n" .
                      "Broken Person,123,invalid@example.com,Delhi\n";

        $file = UploadedFile::fake()->createWithContent('leads_batch.csv', $csvContent);

        Livewire::actingAs($admin)
            ->test(BulkLeadUpload::class)
            ->set('file', $file)
            ->set('columnMapping', [
                'name' => '0',
                'mobile' => '1',
                'email' => '2',
                'city' => '3',
            ])
            ->set('step', 2)
            ->set('projectId', $project->id)
            ->set('leadSourceId', $source->id)
            ->call('processBatch')
            ->assertSet('step', 3);

        $this->assertDatabaseHas('upload_batches', [
            'uploaded_by' => $admin->id,
            'project_id' => $project->id,
            'filename' => 'leads_batch.csv',
            'total_rows' => 3,
            'imported_count' => 1,
            'skipped_count' => 1,
            'failed_count' => 1,
        ]);

        $batch = UploadBatch::first();
        $this->assertNotNull($batch);
        $this->assertCount(1, $batch->error_log);
    }
}
