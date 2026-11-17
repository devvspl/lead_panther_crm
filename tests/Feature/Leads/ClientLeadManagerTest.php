<?php

namespace Tests\Feature\Leads;

use App\Livewire\Leads\{BulkLeadUpload, ClientLeadManager};
use App\Models\{Campaign, Client, Lead, LeadSource, Organization, Project, User};
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class ClientLeadManagerTest extends TestCase
{
    use RefreshDatabase;
    private User $admin;
    private Organization $organization;
    private Client $owner;
    private Project $project;
    private Lead $ours;
    private Lead $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');
        $this->actingAs($this->admin);
        $this->organization = Organization::create(['name' => 'Client scope']);
        $this->owner = Client::create(['organization_id' => $this->organization->id, 'name' => 'Billing owner']);
        $this->project = Project::create(['name' => 'Scoped project', 'client_id' => $this->owner->id]);
        $source = LeadSource::create(['name' => 'Actual source']);
        $campaign = Campaign::create(['project_id' => $this->project->id, 'name' => 'Scoped campaign', 'lead_source_id' => $source->id]);
        $this->ours = Lead::create(['lead_code' => 'LP-CLIENT-OURS', 'name' => 'Our customer', 'mobile' => '+919876543210', 'client_id' => $this->owner->id, 'campaign_id' => $campaign->id, 'lead_source_id' => $source->id, 'status' => 'new', 'current_stage' => 'new']);
        $otherOrg = Organization::create(['name' => 'Other client']);
        $otherOwner = Client::create(['organization_id' => $otherOrg->id, 'name' => 'Other owner']);
        $this->other = Lead::create(['lead_code' => 'LP-CLIENT-OTHER', 'name' => 'Other customer', 'mobile' => '+919999999999', 'client_id' => $otherOwner->id, 'lead_source_id' => $source->id, 'status' => 'new', 'current_stage' => 'new']);
    }

    private function manager()
    {
        return Livewire::test(ClientLeadManager::class, ['organizationId' => $this->organization->id]);
    }

    public function test_import_dropdowns_only_list_related_client_projects_campaigns_and_sources(): void
    {
        $foreignProject = Project::create(['client_id' => $this->other->client_id, 'name' => 'Private foreign project']);
        $foreignSource = LeadSource::create(['name' => 'Private foreign source']);
        Campaign::create(['project_id' => $foreignProject->id, 'name' => 'Private foreign campaign', 'lead_source_id' => $foreignSource->id]);
        $secondProject = Project::create(['client_id' => $this->owner->id, 'name' => 'Second client project']);
        $secondSource = LeadSource::create(['name' => 'Second project source']);
        $secondCampaign = Campaign::create(['project_id' => $secondProject->id, 'name' => 'Second client campaign', 'lead_source_id' => $secondSource->id]);
        $import = Livewire::test(BulkLeadUpload::class, ['organizationId' => $this->organization->id])->set('step', 2)
            ->assertSee('Scoped project')->assertSee('Second client project')->assertDontSee('Private foreign project')
            ->assertDontSee('Private foreign campaign')->assertDontSee('Private foreign source');
        $import->set('projectId', $this->project->id)->assertSee('Scoped campaign')->assertSee('Actual source')
            ->assertDontSee('Second client campaign')->assertDontSee('Second project source');
        $import->set('campaignId', $this->ours->campaign_id)->set('leadSourceId', $this->ours->lead_source_id)
            ->set('projectId', $secondProject->id)->assertSet('campaignId', null)->assertSet('leadSourceId', null)
            ->assertSee('Second client campaign')->assertSee('Second project source')->assertDontSee('Scoped campaign');
        $import->set('leadSourceId', $foreignSource->id)->set('columnMapping.mobile', '0')->call('processBatch')->assertHasErrors('leadSourceId');
    }

    public function test_tab_table_kanban_metrics_and_export_are_client_scoped(): void
    {
        $this->get(route('admin.organizations.show', ['organization' => $this->organization->id, 'tab' => 'leads']))->assertOk()->assertSee('Client Leads Management');
        $manager = $this->manager()->assertSee('LP-CLIENT-OURS')->assertDontSee('LP-CLIENT-OTHER');
        $this->assertSame(1, $manager->instance()->builderLeadKpis['all']['value']);
        $manager->set('filterOrganizationId', (string) $this->other->client->organization_id)->assertSee('LP-CLIENT-OURS')->assertDontSee('LP-CLIENT-OTHER');
        $manager->call('switchViewMode', 'kanban')->assertSee('LP-CLIENT-OURS')->assertDontSee('LP-CLIENT-OTHER');
        Excel::fake();
        $manager->call('exportExcel');
        Excel::assertDownloaded('client-leads-'.$this->organization->id.'.xlsx', fn ($export) => $export->collection()->pluck('id')->all() === [$this->ours->id]);
        $manager->set('search', 'Nothing matches')->call('exportExcel');
        Excel::assertDownloaded('client-leads-'.$this->organization->id.'.xlsx', fn ($export) => $export->collection()->isEmpty());
    }

    public function test_manual_entry_uses_project_owner_and_rejects_cross_client_campaigns(): void
    {
        $manager = $this->manager()->call('openPullLeadsModal')->set('manualName', 'Manual customer')->set('manualMobile', '9123456789')->set('manualProjectId', $this->project->id)->call('createManualLead')->assertHasNoErrors();
        $this->assertDatabaseHas('leads', ['name' => 'Manual customer', 'client_id' => $this->owner->id]);
        $manager->set('manualName', 'Duplicate')->set('manualMobile', '9123456789')->set('manualProjectId', $this->project->id)->call('createManualLead');
        $this->assertSame(1, Lead::where('mobile', '9123456789')->count());
        $manager->set('manualProjectId', 999999)->call('createManualLead')->assertForbidden();
    }

    public function test_bulk_status_assignment_delete_and_forged_ids(): void
    {
        $executive = User::factory()->create(['organization_id' => $this->organization->id, 'status' => 'active']);
        $executive->assignRole('Executive');
        $this->manager()->set('selectedLeadIds', [$this->ours->id])->set('assignUserId', $executive->id)->call('executeAssignment')->assertHasNoErrors();
        $this->assertSame($executive->id, $this->ours->fresh()->assigned_to);
        $this->manager()->set('selectedRows', [$this->ours->id])->set('bulkStage', 'contacted')->call('changeSelectedStatus')->assertHasNoErrors();
        $this->assertSame('contacted', $this->ours->fresh()->current_stage);
        $this->manager()->set('selectedRows', [$this->other->id])->set('bulkStage', 'contacted')->call('changeSelectedStatus')->assertForbidden();
        $this->manager()->set('selectedLeadIds', [$this->ours->id])->set('assignUserId', $this->other->client_id)->call('executeAssignment')->assertForbidden();
        $this->manager()->set('selectedRows', [$this->other->id])->call('deleteSelectedLeads')->assertForbidden();
        $this->manager()->set('selectedRows', [$this->ours->id])->call('deleteSelectedLeads')->assertHasNoErrors();
        $this->assertDatabaseMissing('leads', ['id' => $this->ours->id]);
        $this->assertDatabaseHas('audit_logs', ['subject_id' => $this->ours->id, 'action' => 'lead.deleted']);
    }

    public function test_import_maps_first_column_validates_rows_and_detects_duplicates(): void
    {
        $file = UploadedFile::fake()->createWithContent('client.csv', "Mobile,Name,Email\n9123000001,Imported,valid@example.test\n9123000001,Duplicate,valid@example.test\n9123000002,Bad email,invalid-email\n123,Bad mobile,bad@example.test\n");
        Livewire::test(BulkLeadUpload::class, ['organizationId' => $this->organization->id])->set('file', $file)
            ->set('columnMapping', ['mobile' => '0', 'name' => '1', 'email' => '2'])->call('proceedToAttribution')->assertSet('step', 2)
            ->set('projectId', $this->project->id)->call('processBatch')->assertSet('step', 3)->assertHasNoErrors();
        $this->assertDatabaseHas('leads', ['name' => 'Imported', 'client_id' => $this->owner->id]);
        $this->assertDatabaseHas('upload_batches', ['project_id' => $this->project->id, 'imported_count' => 1, 'skipped_count' => 1, 'failed_count' => 2]);
        Livewire::test(BulkLeadUpload::class, ['organizationId' => $this->other->client->organization_id])->set('projectId', $this->project->id)->set('columnMapping.mobile', '0')->call('processBatch')->assertForbidden();
    }

    public function test_ordinary_roles_cannot_mount_client_management_or_import_context(): void
    {
        $this->actingAs($this->owner);
        $this->manager()->assertForbidden();
        Livewire::test(BulkLeadUpload::class, ['organizationId' => $this->organization->id])->assertForbidden();
    }

    public function test_pending_internal_and_old_leads_are_visible_and_date_filter_applies_to_export(): void
    {
        $this->ours->forceFill(['created_at' => now()->subMonths(2), 'internal_processing_pending' => true, 'delivery_pending' => true])->saveQuietly();
        $manager = $this->manager()->assertSee('LP-CLIENT-OURS');
        $manager->set('deliveryFilter', 'internal')->assertSee('LP-CLIENT-OURS');
        $manager->set('analyticsRange', 'today')->assertDontSee('LP-CLIENT-OURS');
        Excel::fake();
        $manager->call('exportExcel');
        Excel::assertDownloaded('client-leads-'.$this->organization->id.'.xlsx', fn ($export) => $export->collection()->isEmpty());
        $manager->call('resetFilters')->assertSee('LP-CLIENT-OURS');
    }

    public function test_xlsx_import_and_financial_delete_protection(): void
    {
        $sheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet->getActiveSheet()->fromArray([['Mobile', 'Name'], ['9123000022', 'Spreadsheet customer']]);
        ob_start();
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($sheet))->save('php://output');
        $bytes = ob_get_clean();
        $file = UploadedFile::fake()->createWithContent('client.xlsx', $bytes);
        $this->assertSame('Xlsx', \PhpOffice\PhpSpreadsheet\IOFactory::identify($file->getRealPath()));
        $import = Livewire::test(BulkLeadUpload::class, ['organizationId' => $this->organization->id])->set('file', $file);
        $this->assertCount(1, $import->instance()->allCsvRows, json_encode($import->effects['dispatches'] ?? []));
        $import->set('columnMapping', ['mobile' => '0', 'name' => '1'])->set('projectId', $this->project->id)
            ->call('processBatch')->assertSet('step', 3);
        $this->assertSame(1, $import->instance()->completedBatch->imported_count, json_encode($import->instance()->completedBatch->toArray()));
        $this->assertDatabaseHas('leads', ['name' => 'Spreadsheet customer', 'client_id' => $this->owner->id]);
        \App\Models\CreditTransaction::create(['client_id' => $this->owner->id, 'lead_id' => $this->ours->id, 'credit_before' => 10, 'credit_used' => 1, 'credit_after' => 9, 'transaction_type' => 'deduct', 'created_at' => now()]);
        $this->manager()->set('selectedRows', [$this->ours->id])->call('deleteSelectedLeads')->assertHasErrors('selectedRows');
        $this->assertDatabaseHas('leads', ['id' => $this->ours->id]);
    }
}
