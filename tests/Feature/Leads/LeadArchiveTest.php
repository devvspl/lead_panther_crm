<?php

namespace Tests\Feature\Leads;

use App\Livewire\Admin\ArchivedLeads;
use App\Livewire\Leads\{LeadArchiveDialog, LeadDetail, LeadKanban};
use App\Models\{AuditLog, Client, CreditTransaction, Followup, Lead, LeadAssignment, LeadSource, User};
use App\Services\{LeadArchiveService, LeadDistributionService};
use Database\Seeders\DeltekSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LeadArchiveTest extends TestCase
{
    use RefreshDatabase;
    private User $admin;
    private User $builder;
    private User $exec;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DeltekSeeder::class);
        $this->admin = User::factory()->create(['status'=>'active']);
        $this->admin->assignRole('Super Admin');
        $this->builder = User::where('email','deltek@deltek.leadpanther.test')->firstOrFail();
        $this->exec = User::where('email','se1@deltek.leadpanther.test')->firstOrFail();
        $this->actingAs($this->admin);
    }

    private function lead(bool $assigned = true): Lead
    {
        return Lead::withoutGlobalScopes()->create([
            'lead_code'=>Lead::generateUniqueLeadCode(), 'lead_source_id'=>LeadSource::firstOrCreate(['name'=>'Archive tests'])->id,
            'client_id'=>$this->builder->id, 'assigned_to'=>$assigned ? $this->exec->id : null,
            'name'=>'Archive workflow customer', 'mobile'=>'9000012345', 'status'=>'interested', 'current_stage'=>'interested',
            'delivery_pending'=>false, 'internal_processing_pending'=>false, 'delivered_at'=>now(),
        ]);
    }

    public function test_super_admin_can_open_the_full_archived_leads_page_with_the_existing_layout(): void
    {
        $lead = $this->lead();
        app(LeadArchiveService::class)->archive($lead->id, 'Full-page route check');
        $this->get(route('admin.archived-leads'))->assertOk()
            ->assertSee('Archived Leads')->assertSee('sidebar-main')->assertSee($lead->name);
    }

    public function test_archive_and_restore_preserve_data_and_history_and_hold_automatic_assignment(): void
    {
        $lead = $this->lead();
        $followup = Followup::create(['lead_id'=>$lead->id,'created_by'=>$this->exec->id,'due_at'=>now()->addDay(),'note'=>'Preserve callback','status'=>'pending']);
        LeadAssignment::create(['lead_id'=>$lead->id,'assigned_to'=>$this->exec->id,'assigned_by'=>$this->admin->id,'assigned_at'=>now()]);
        $this->postJson(route('admin.leads.archive',$lead->id), ['confirmed'=>true,'reason'=>'Duplicate review'])->assertNoContent();
        $this->assertDatabaseHas('leads',['id'=>$lead->id,'archive_reason'=>'Duplicate review','assigned_to'=>$this->exec->id,'current_stage'=>'interested']);
        $this->assertDatabaseHas('followups',['id'=>$followup->id,'note'=>'Preserve callback']);
        $this->assertFalse(Lead::withoutGlobalScopes()->whereKey($lead->id)->exists());
        $this->assertFalse(Lead::withoutGlobalScopes()->where('id',0)->orWhere('id',$lead->id)->exists());
        $this->assertFalse(Followup::withoutGlobalScopes()->whereKey($followup->id)->exists());
        $this->assertFalse(LeadAssignment::withoutGlobalScopes()->where('lead_id',$lead->id)->exists());
        Livewire::test(ArchivedLeads::class)->assertSee($lead->name)->call('details',$lead->id)->assertSee('Preserve callback')->assertSee('lead.archived');
        $this->postJson(route('admin.leads.restore',$lead->id), ['confirmed'=>true])->assertNoContent();
        $restored = Lead::withoutGlobalScopes()->findOrFail($lead->id);
        $this->assertSame($this->exec->id,$restored->assigned_to);
        $this->assertSame('interested',$restored->current_stage);
        $this->assertTrue($restored->archive_assignment_hold);
        $this->assertNull($restored->archived_at);
        $this->assertSame(1,Followup::where('lead_id',$lead->id)->count());
        $this->assertSame(1,LeadAssignment::where('lead_id',$lead->id)->count());
        $this->assertSame(1,AuditLog::where('action','lead.archived')->where('subject_id',$lead->id)->count());
        $this->assertSame(1,AuditLog::where('action','lead.restored')->where('subject_id',$lead->id)->count());
        $this->actingAs($this->builder);
        $this->assertTrue(Lead::panelQuery()->whereKey($lead->id)->exists());
        Livewire::test(LeadDetail::class)->call('openLead',$lead->id)->set('activeTab','timeline')->assertSee('lead.archived')->assertSee('lead.restored');
    }

    public function test_all_non_super_admin_roles_are_denied_ui_endpoints_and_database_archive_access(): void
    {
        $lead = $this->lead();
        app(LeadArchiveService::class)->archive($lead->id, 'Private archived lead');
        foreach (['Client Admin','Client','Builder/Developer','Team Leader','Executive'] as $role) {
            Role::findOrCreate($role,'web');
            $user = User::factory()->create(['status'=>'active','organization_id'=>$this->builder->organization_id]);
            $user->assignRole($role);
            $this->actingAs($user);
            $this->get(route('admin.archived-leads'))->assertForbidden();
            $this->postJson(route('admin.leads.archive',$lead->id),['confirmed'=>true])->assertForbidden();
            $this->postJson(route('admin.leads.restore',$lead->id),['confirmed'=>true])->assertForbidden();
            $this->deleteJson(route('admin.leads.purge',$lead->id),['confirmed'=>true])->assertForbidden();
            $this->getJson('/api/leads/'.$lead->id)->assertNotFound();
            Livewire::test(ArchivedLeads::class)->assertForbidden();
            Livewire::test(LeadArchiveDialog::class)->assertForbidden();
            $this->assertFalse(Lead::withoutGlobalScopes()->whereKey($lead->id)->exists());
            try {
                Lead::withoutGlobalScopes()->withArchived()->find($lead->id);
                $this->fail('Archived access must require Super Admin.');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403,$e->getStatusCode()); }
        }
    }

    public function test_confirmation_is_required_and_purge_cascades_but_retains_immutable_audit(): void
    {
        $lead = $this->lead();
        $followup = Followup::create(['lead_id'=>$lead->id,'created_by'=>$this->exec->id,'due_at'=>now(),'status'=>'pending']);
        $this->postJson(route('admin.leads.archive',$lead->id),[])->assertUnprocessable();
        $this->deleteJson(route('admin.leads.purge',$lead->id),['confirmed'=>true])->assertNotFound();
        app(LeadArchiveService::class)->archive($lead->id,'Remove test data');
        $this->deleteJson(route('admin.leads.purge',$lead->id),[])->assertUnprocessable();
        $this->deleteJson(route('admin.leads.purge',$lead->id),['confirmed'=>true,'reason'=>'Confirmed removal'])->assertNoContent();
        $this->assertDatabaseMissing('leads',['id'=>$lead->id]);
        $this->assertDatabaseMissing('followups',['id'=>$followup->id]);
        $this->assertDatabaseHas('audit_logs',['action'=>'lead.permanently_deleted','subject_id'=>$lead->id]);
        $this->assertDatabaseHas('audit_logs',['action'=>'lead.archived','subject_id'=>$lead->id]);
    }

    public function test_financial_retention_blocks_purge_and_rolls_back_deletion_audit(): void
    {
        $lead = $this->lead();
        CreditTransaction::create(['client_id'=>$this->builder->id,'lead_id'=>$lead->id,'credit_before'=>100,'credit_used'=>10,'credit_after'=>90,'transaction_type'=>'deduct','created_at'=>now()]);
        app(LeadArchiveService::class)->archive($lead->id);
        $this->deleteJson(route('admin.leads.purge',$lead->id),['confirmed'=>true])->assertUnprocessable();
        $this->assertDatabaseHas('leads',['id'=>$lead->id]);
        $this->assertFalse(AuditLog::where('action','lead.permanently_deleted')->where('subject_id',$lead->id)->exists());
        $this->assertSame(1,CreditTransaction::where('lead_id',$lead->id)->count());
    }

    public function test_restored_unassigned_lead_is_not_automatically_distributed_and_exports_are_filtered(): void
    {
        $lead = $this->lead(false);
        app(LeadArchiveService::class)->archive($lead->id,'Review pending');
        Excel::fake();
        Livewire::test(ArchivedLeads::class)->set('search',$lead->lead_code)->call('exportExcel');
        Excel::assertDownloaded('archived-leads.xlsx');
        app(LeadArchiveService::class)->restore($lead->id);
        $service = app(LeadDistributionService::class);
        $this->assertFalse($service->assignmentPendingLeads($this->builder->organization_id)->whereKey($lead->id)->exists());
        $this->assertSame('held',$service->distribute(Lead::withoutGlobalScopes()->findOrFail($lead->id))['status']);
        $this->assertNull(Lead::withoutGlobalScopes()->findOrFail($lead->id)->assigned_to);
        Livewire::test(ArchivedLeads::class)->assertDontSee($lead->name);
    }

    public function test_archived_lead_is_excluded_from_client_lists_dashboards_reports_and_signed_documents(): void
    {
        $lead = $this->lead();
        app(LeadArchiveService::class)->archive($lead->id);
        $this->actingAs($this->builder);
        Livewire::test(LeadKanban::class)->assertDontSee($lead->name)->assertDontSee('archive-lead');
        Livewire::test(\App\Livewire\Dashboard\DashboardShell::class,['role'=>'builder'])
            ->assertViewHas('builderData', fn($data)=>$data['kpis']['total_leads']['value'] === 0);
        $this->actingAs($this->admin);
        Livewire::test(\App\Livewire\Reports\SourcePerformance::class)->assertViewHas('chartReportData', fn($data)=>count($data) === 0);
        $this->actingAs($this->builder);
        \Illuminate\Support\Facades\Storage::fake('documents');
        $path = $this->builder->id.'/'.$lead->id.'/payments/proof.pdf';
        \Illuminate\Support\Facades\Storage::disk('documents')->put($path,'test');
        $url = \Illuminate\Support\Facades\URL::signedRoute('documents.download',['path'=>$path]);
        $this->get($url)->assertNotFound();
        $this->actingAs($this->admin);
        app(LeadArchiveService::class)->restore($lead->id);
        $this->actingAs($this->builder);
        Livewire::test(LeadKanban::class)->assertSee($lead->name);
        Livewire::test(\App\Livewire\Dashboard\DashboardShell::class,['role'=>'builder'])
            ->assertViewHas('builderData', fn($data)=>$data['kpis']['total_leads']['value'] === 1);
    }

    public function test_non_admin_cannot_reuse_an_admin_livewire_snapshot_to_restore_a_lead(): void
    {
        $lead = $this->lead();
        app(LeadArchiveService::class)->archive($lead->id);
        $component = Livewire::test(ArchivedLeads::class)->call('prepare',$lead->id,'restore');
        $snapshot = $component->snapshot;
        $this->actingAs($this->exec);
        $this->postJson(route('default.livewire.update'), [
            '_token'=>csrf_token(), 'components'=>[[
                'snapshot'=>json_encode($snapshot), 'updates'=>['confirmed'=>true],
                'calls'=>[['method'=>'execute','params'=>[],'path'=>'']],
            ]],
        ])->assertForbidden();
        $this->assertDatabaseHas('leads',['id'=>$lead->id,'archived_by'=>$this->admin->id]);
    }

    public function test_archive_confirmation_and_selected_rows_refresh_use_the_existing_lead_screen(): void
    {
        $lead = $this->lead();
        $dialog = Livewire::test(LeadArchiveDialog::class)->call('open',$lead->id)
            ->call('archive')->assertHasErrors('confirmed');
        $this->assertNull(Lead::withoutGlobalScopes()->findOrFail($lead->id)->archived_at);
        $dialog->set('confirmed',true)->set('reason','Archive from the lead actions menu')
            ->call('archive')->assertHasNoErrors()->assertDispatched('lead-archived');
        Livewire::test(LeadKanban::class)->set('selectedRows',[(string)$lead->id])->call('refreshBoard')->assertSet('selectedRows',[]);
        Livewire::test(ArchivedLeads::class)->call('prepare',$lead->id,'restore')
            ->call('execute')->assertHasErrors('confirmed')->set('confirmed',true)->call('execute')->assertHasNoErrors();
        $this->assertTrue(Lead::withoutGlobalScopes()->findOrFail($lead->id)->archive_assignment_hold);
    }
}
