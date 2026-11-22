<?php

namespace Tests\Feature\Leads;

use Tests\TestCase;
use App\Models\User;
use App\Models\Organization;
use App\Models\Client;
use App\Models\Project;
use App\Models\Campaign;
use App\Models\LeadSource;
use App\Models\UploadBatch;
use App\Livewire\Leads\BulkLeadUpload;
use App\Livewire\Leads\UploadHistory;
use Livewire\Livewire;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

class UploadBatchDownloadTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Organization $org;
    private Client $client;
    private Project $project;
    private LeadSource $source;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Client', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Sales Executive', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');

        $this->org = Organization::create(['name' => 'Test Org', 'type' => 'builder']);
        $this->client = Client::create(['organization_id' => $this->org->id, 'name' => 'Client Org User']);
        $this->project = Project::create(['client_id' => $this->client->id, 'name' => 'Test Project']);
        $this->source = LeadSource::firstOrCreate(['name' => 'csv_bulk']);
    }

    public function test_batch_stores_original_uploaded_file_path_on_upload(): void
    {
        $csvContent = "Full Name,Mobile Number,Email Address\nAlice,+919876543211,alice@example.com\n";
        $file = UploadedFile::fake()->createWithContent('customers_q4.csv', $csvContent);

        Livewire::actingAs($this->admin)
            ->test(BulkLeadUpload::class)
            ->set('file', $file)
            ->set('columnMapping', [
                'name' => '0',
                'mobile' => '1',
                'email' => '2',
            ])
            ->set('step', 2)
            ->set('projectId', $this->project->id)
            ->set('leadSourceId', $this->source->id)
            ->call('processBatch')
            ->assertSet('step', 3);

        $batch = UploadBatch::latest('id')->first();
        $this->assertNotNull($batch);
        $this->assertSame('customers_q4.csv', $batch->filename);
        $this->assertSame('customers_q4.csv', $batch->original_filename);
        $this->assertNotEmpty($batch->file_path);
        $this->assertTrue(Storage::disk('local')->exists($batch->file_path));
        $this->assertTrue($batch->hasOriginalFile());
    }

    public function test_download_original_file_successful_with_original_name_and_content(): void
    {
        $content = "Full Name,Mobile Number\nBob,+919876543212\n";
        $fakePath = 'imports/batches/2026/11/bob_original.csv';
        Storage::disk('local')->put($fakePath, $content);

        $batch = UploadBatch::create([
            'uploaded_by' => $this->admin->id,
            'project_id' => $this->project->id,
            'filename' => 'bob_original.csv',
            'original_filename' => 'bob_original.csv',
            'file_path' => $fakePath,
            'total_rows' => 1,
            'imported_count' => 1,
            'skipped_count' => 0,
            'failed_count' => 0,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('leads.upload-batches.download', $batch->id));

        $response->assertOk();
        $this->assertStringContainsString('bob_original.csv', $response->headers->get('content-disposition'));
        $this->assertSame($content, $response->streamedContent());
    }

    public function test_supports_xls_xlsx_and_csv_file_types(): void
    {
        $types = [
            ['name' => 'leads_data.xlsx', 'content' => 'fake-xlsx-binary-stream'],
            ['name' => 'legacy_leads.xls', 'content' => 'fake-xls-binary-stream'],
            ['name' => 'plain_leads.csv', 'content' => "Col1,Col2\nVal1,Val2\n"],
        ];

        foreach ($types as $idx => $t) {
            $path = "imports/batches/2026/11/batch_{$idx}_file";
            Storage::disk('local')->put($path, $t['content']);

            $batch = UploadBatch::create([
                'uploaded_by' => $this->admin->id,
                'project_id' => $this->project->id,
                'filename' => $t['name'],
                'original_filename' => $t['name'],
                'file_path' => $path,
                'total_rows' => 5,
                'imported_count' => 5,
            ]);

            $response = $this->actingAs($this->admin)
                ->get(route('leads.upload-batches.download', $batch->id));

            $response->assertOk();
            $this->assertStringContainsString($t['name'], $response->headers->get('content-disposition'));
            $this->assertSame($t['content'], $response->streamedContent());
        }
    }

    public function test_download_authorization_enforced(): void
    {
        $content = "Name,Mobile\nJane,+919999988888\n";
        $path = 'imports/batches/2026/11/jane.csv';
        Storage::disk('local')->put($path, $content);

        $batch = UploadBatch::create([
            'uploaded_by' => $this->admin->id,
            'project_id' => $this->project->id,
            'filename' => 'jane.csv',
            'original_filename' => 'jane.csv',
            'file_path' => $path,
            'total_rows' => 1,
            'imported_count' => 1,
        ]);

        // 1. Super Admin -> Allowed
        $this->actingAs($this->admin)
            ->get(route('leads.upload-batches.download', $batch->id))
            ->assertOk();

        // 2. Sales Executive -> 403 Forbidden
        $salesExec = User::factory()->create(['organization_id' => $this->org->id]);
        $salesExec->assignRole('Sales Executive');
        $this->actingAs($salesExec)
            ->get(route('leads.upload-batches.download', $batch->id))
            ->assertForbidden();

        // 3. Foreign user from different organization -> 403 Forbidden
        $otherOrg = Organization::create(['name' => 'Other Org', 'type' => 'builder']);
        $foreignUser = User::factory()->create(['organization_id' => $otherOrg->id]);
        $foreignUser->assignRole('Sales Executive');
        $this->actingAs($foreignUser)
            ->get(route('leads.upload-batches.download', $batch->id))
            ->assertForbidden();

        // 4. Unauthenticated guest -> Redirected to login
        auth()->logout();
        $this->get(route('leads.upload-batches.download', $batch->id))
            ->assertRedirect(route('login'));
    }

    public function test_directory_traversal_is_blocked(): void
    {
        $batch = UploadBatch::create([
            'uploaded_by' => $this->admin->id,
            'project_id' => $this->project->id,
            'filename' => 'malicious.csv',
            'original_filename' => 'malicious.csv',
            'file_path' => '../../../../etc/passwd',
            'total_rows' => 1,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('leads.upload-batches.download', $batch->id));

        $response->assertRedirect(route('leads.upload-history'));
        $response->assertSessionHas('error');
    }

    public function test_unavailable_file_handling_without_forging_fake_excel(): void
    {
        // Batch where file was deleted from disk or never stored
        $batch = UploadBatch::create([
            'uploaded_by' => $this->admin->id,
            'project_id' => $this->project->id,
            'filename' => 'missing_file.xlsx',
            'original_filename' => 'missing_file.xlsx',
            'file_path' => 'imports/batches/2026/11/does_not_exist.xlsx',
            'total_rows' => 10,
            'imported_count' => 10,
        ]);

        $this->assertFalse($batch->hasOriginalFile());

        $response = $this->actingAs($this->admin)
            ->get(route('leads.upload-batches.download', $batch->id));

        $response->assertRedirect(route('leads.upload-history'));
        $response->assertSessionHas('error', 'Original file unavailable for this batch.');
    }

    public function test_upload_history_ui_displays_download_button_and_unavailable_label(): void
    {
        // 1. Batch WITH stored original file
        $path = 'imports/batches/2026/11/available_leads.xlsx';
        Storage::disk('local')->put($path, 'dummy-content');

        $availableBatch = UploadBatch::create([
            'uploaded_by' => $this->admin->id,
            'project_id' => $this->project->id,
            'filename' => 'available_leads.xlsx',
            'original_filename' => 'available_leads.xlsx',
            'file_path' => $path,
            'total_rows' => 5,
            'imported_count' => 5,
            'failed_count' => 0,
        ]);

        // 2. Batch WITHOUT stored original file (historical batch)
        $historicalBatch = UploadBatch::create([
            'uploaded_by' => $this->admin->id,
            'project_id' => $this->project->id,
            'filename' => 'historical_leads.xlsx',
            'original_filename' => null,
            'file_path' => null,
            'total_rows' => 8,
            'imported_count' => 8,
            'failed_count' => 0,
        ]);

        // 3. Batch WITH errors
        $failedBatch = UploadBatch::create([
            'uploaded_by' => $this->admin->id,
            'project_id' => $this->project->id,
            'filename' => 'mixed_leads.csv',
            'original_filename' => 'mixed_leads.csv',
            'file_path' => null,
            'total_rows' => 10,
            'imported_count' => 7,
            'failed_count' => 3,
            'error_log' => [['row' => 2, 'data' => 'bad row', 'reason' => 'invalid phone']],
        ]);

        Livewire::actingAs($this->admin)
            ->test(UploadHistory::class)
            ->assertSee('Download')
            ->assertSee(route('leads.upload-batches.download', $availableBatch->id))
            ->assertSee('Original file unavailable')
            ->assertSee('Clean Import')
            ->assertSee('Error CSV');
    }
}
