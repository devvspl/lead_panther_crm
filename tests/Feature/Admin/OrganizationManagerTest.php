<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Organization;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Project;
use App\Models\CreditWallet;
use App\Models\CreditTransaction;
use App\Models\LeadSource;
use App\Livewire\Admin\OrganizationManager;
use App\Livewire\Admin\OrganizationDetail;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

class OrganizationManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_organization_manager_renders_and_opens_modal_with_create_and_import_tabs(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        Livewire::actingAs($admin)
            ->test(OrganizationManager::class)
            ->assertSee('Clients Management')
            ->assertSee('Register Client')
            ->call('openRegisterOrgModal', 'create')
            ->assertSet('showCreateOrgModal', true)
            ->assertSet('orgModalTab', 'create')
            ->assertSee('Create Client')
            ->assertSee('Import Clients')
            ->set('orgModalTab', 'import')
            ->assertSee('Download Import Template')
            ->assertSee('Required Columns & Sample Format')
            ->assertSee('Upload File (Excel or CSV)')
            ->call('closeRegisterOrgModal')
            ->assertSet('showCreateOrgModal', false);
    }

    public function test_create_organization_from_modal(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        Livewire::actingAs($admin)
            ->test(OrganizationManager::class)
            ->call('openRegisterOrgModal', 'create')
            ->set('name', 'Sobha Developers Ltd')
            ->set('status', 'active')
            ->call('createOrganization')
            ->assertSet('showCreateOrgModal', false)
            ->assertDispatched('toast');

        $this->assertDatabaseHas('organizations', [
            'name' => 'Sobha Developers Ltd',
            'status' => 'active',
        ]);
    }

    public function test_download_import_template_excel_and_csv(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $component = Livewire::actingAs($admin)->test(OrganizationManager::class);

        $xlsxResponse = $component->call('downloadImportTemplate');
        $this->assertNotNull($xlsxResponse);

        $csvResponse = $component->call('downloadImportTemplateCsv');
        $this->assertNotNull($csvResponse);
    }

    public function test_import_organizations_valid_csv(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $csvContent = "Organization Name,Status\n" .
            "Godrej Properties,active\n" .
            "Anarock Property Consultants,active\n" .
            "Central Platform Ops,suspended\n";

        $file = UploadedFile::fake()->createWithContent('organizations.csv', $csvContent);

        Livewire::actingAs($admin)
            ->test(OrganizationManager::class)
            ->call('openRegisterOrgModal', 'import')
            ->set('importFile', $file)
            ->assertCount('importRows', 3)
            ->assertCount('importErrors', 0)
            ->call('processImport')
            ->assertSet('showCreateOrgModal', false)
            ->assertDispatched('toast');

        $this->assertDatabaseHas('organizations', [
            'name' => 'Godrej Properties',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('organizations', [
            'name' => 'Anarock Property Consultants',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('organizations', [
            'name' => 'Central Platform Ops',
            'status' => 'suspended',
        ]);

        // Users automatically created in users table for each imported organization
        $godrejOrg = Organization::where('name', 'Godrej Properties')->first();
        $this->assertNotNull($godrejOrg);
        $this->assertDatabaseHas('users', [
            'organization_id' => $godrejOrg->id,
            'name' => 'Godrej Properties',
        ]);

        $anarockOrg = Organization::where('name', 'Anarock Property Consultants')->first();
        $this->assertNotNull($anarockOrg);
        $this->assertDatabaseHas('users', [
            'organization_id' => $anarockOrg->id,
            'name' => 'Anarock Property Consultants',
        ]);
    }

    public function test_import_organizations_and_users_both_tables_with_broker_and_builder_roles(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $csvContent = "Organization Name,Status,User Name,User Email,User Phone,User Role\n" .
            "Prestige Estates Ltd,active,Rajesh Sharma,rajesh.test@prestige.com,+919876543210,Builder/Developer\n" .
            "Apex Realty Advisors,active,Pooja Mehta,pooja.test@apexrealty.com,+919812345678,Broker\n" .
            "Square Yards Advisory,active,Vikram Singh,vikram.test@squareyards.com,+919822334455,Executive\n";

        $file = UploadedFile::fake()->createWithContent('org_and_users.csv', $csvContent);

        Livewire::actingAs($admin)
            ->test(OrganizationManager::class)
            ->call('openRegisterOrgModal', 'import')
            ->set('importFile', $file)
            ->assertCount('importRows', 3)
            ->assertCount('importErrors', 0)
            ->call('processImport')
            ->assertSet('showCreateOrgModal', false)
            ->assertDispatched('toast');

        // Check organizations table
        $this->assertDatabaseHas('organizations', [
            'name' => 'Prestige Estates Ltd',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('organizations', [
            'name' => 'Apex Realty Advisors',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('organizations', [
            'name' => 'Square Yards Advisory',
            'status' => 'active',
        ]);

        // Check users table
        $prestigeOrg = Organization::where('name', 'Prestige Estates Ltd')->first();
        $this->assertNotNull($prestigeOrg);
        $builderUser = User::where('email', 'rajesh.test@prestige.com')->first();
        $this->assertNotNull($builderUser);
        $this->assertEquals('Rajesh Sharma', $builderUser->name);
        $this->assertEquals('+919876543210', $builderUser->phone);
        $this->assertEquals($prestigeOrg->id, $builderUser->organization_id);
        $this->assertTrue($builderUser->hasRole('Builder/Developer'));

        $apexOrg = Organization::where('name', 'Apex Realty Advisors')->first();
        $this->assertNotNull($apexOrg);
        $brokerUser = User::where('email', 'pooja.test@apexrealty.com')->first();
        $this->assertNotNull($brokerUser);
        $this->assertEquals('Pooja Mehta', $brokerUser->name);
        $this->assertEquals('+919812345678', $brokerUser->phone);
        $this->assertEquals($apexOrg->id, $brokerUser->organization_id);
        $this->assertTrue($brokerUser->hasRole('Broker'));

        // Check wallets created
        $this->assertDatabaseHas('client_wallets', [
            'client_id' => $builderUser->id,
            'balance' => 0.00,
        ]);
        $this->assertDatabaseHas('client_wallets', [
            'client_id' => $brokerUser->id,
            'balance' => 0.00,
        ]);
    }

    public function test_import_organizations_with_row_errors_and_download_errors_csv(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        Organization::create([
            'name' => 'Existing Builder Ltd',
            'status' => 'active',
        ]);

        $csvContent = "Organization Name,Status\n" .
            "Existing Builder Ltd,active\n" . // duplicate in DB
            ",active\n" . // missing name
            "Duplicate Batch Corp,active\n" .
            "Duplicate Batch Corp,active\n" . // duplicate in file
            "Valid Channel Partner,active\n"; // valid

        $file = UploadedFile::fake()->createWithContent('organizations_with_errors.csv', $csvContent);

        $component = Livewire::actingAs($admin)
            ->test(OrganizationManager::class)
            ->call('openRegisterOrgModal', 'import')
            ->set('importFile', $file)
            ->assertCount('importErrors', 3)
            ->assertCount('importRows', 2);

        $errorsResponse = $component->call('downloadImportErrorsCsv');
        $this->assertNotNull($errorsResponse);

        $component->call('processImport')
            ->assertSet('showCreateOrgModal', false)
            ->assertDispatched('toast');

        // Only valid row imported
        $this->assertDatabaseHas('organizations', [
            'name' => 'Valid Channel Partner',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('organizations', [
            'name' => 'Duplicate Batch Corp',
            'status' => 'active',
        ]);
    }

    public function test_import_from_downloaded_excel_template_handles_banners_and_headers(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        // Generate the actual Excel file from OrganizationImportTemplateExport
        $export = new \App\Exports\OrganizationImportTemplateExport();
        $tempPath = tempnam(sys_get_temp_dir(), 'org_template_') . '.xlsx';
        \Maatwebsite\Excel\Facades\Excel::store($export, basename($tempPath), 'local');
        $storedPath = \Illuminate\Support\Facades\Storage::disk('local')->path(basename($tempPath));

        $fileContent = file_get_contents($storedPath);
        $uploadedFile = UploadedFile::fake()->createWithContent(
            'Organization_Import_Template.xlsx',
            $fileContent
        );

        $component = Livewire::actingAs($admin)
            ->test(OrganizationManager::class)
            ->call('openRegisterOrgModal', 'import')
            ->set('importFile', $uploadedFile);

        // Crucial assertions: headers and banners are NOT parsed as data rows, zero errors
        $component->assertCount('importErrors', 0)
            ->assertCount('importRows', 6);

        $component->call('processImport')
            ->assertSet('showCreateOrgModal', false)
            ->assertDispatched('toast');

        $this->assertDatabaseHas('organizations', [
            'name' => 'Prestige Estates Projects Ltd',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('organizations', [
            'name' => 'Apex Realty Advisory Partners',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('users', [
            'name' => 'Rajesh Sharma',
            'email' => 'rajesh@prestige.com',
        ]);

        $this->assertDatabaseHas('users', [
            'name' => 'Pooja Mehta',
            'email' => 'pooja@apexrealty.com',
        ]);

        // Cleanup
        \Illuminate\Support\Facades\Storage::disk('local')->delete(basename($tempPath));
    }

    public function test_open_organization_details_modal_in_view_mode_and_switch_modes(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $org = Organization::create([
            'name' => 'Lodha Group Commercials',
            'status' => 'active',
        ]);

        Livewire::actingAs($admin)
            ->test(OrganizationManager::class)
            ->call('openOrgDetailsModal', $org->id)
            ->assertSet('showOrgDetailsModal', true)
            ->assertSet('orgDetailsMode', 'view')
            ->assertSet('editingOrgId', $org->id)
            ->assertSee('Lodha Group Commercials')
            ->assertSee('#CLI-' . str_pad($org->id, 4, '0', STR_PAD_LEFT))
            ->call('switchOrgDetailsMode', 'edit')
            ->assertSet('orgDetailsMode', 'edit')
            ->assertSet('editName', 'Lodha Group Commercials')
            ->assertSet('editStatus', 'active')
            ->call('closeOrgDetailsModal')
            ->assertSet('showOrgDetailsModal', false)
            ->assertSet('editingOrgId', null);
    }

    public function test_update_organization_details_saves_and_persists(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $org = Organization::create([
            'name' => 'Tata Housing Development',
            'status' => 'active',
        ]);

        Livewire::actingAs($admin)
            ->test(OrganizationManager::class)
            ->call('openOrgDetailsModal', $org->id, 'edit')
            ->set('editName', 'Tata Realty and Infrastructure Ltd')
            ->set('editStatus', 'suspended')
            ->call('updateOrganization')
            ->assertSet('orgDetailsMode', 'view')
            ->assertDispatched('toast');

        $this->assertDatabaseHas('organizations', [
            'id' => $org->id,
            'name' => 'Tata Realty and Infrastructure Ltd',
            'status' => 'suspended',
        ]);
    }

    public function test_update_organization_validation(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $org = Organization::create([
            'name' => 'Puravankara Projects',
            'status' => 'active',
        ]);

        Livewire::actingAs($admin)
            ->test(OrganizationManager::class)
            ->call('openOrgDetailsModal', $org->id, 'edit')
            ->set('editName', '')
            ->set('editStatus', 'invalid_status')
            ->call('updateOrganization')
            ->assertHasErrors(['editName', 'editStatus']);

        $this->assertDatabaseHas('organizations', [
            'id' => $org->id,
            'name' => 'Puravankara Projects',
            'status' => 'active',
        ]);
    }

    public function test_manage_users_modal_loads_dynamic_roles_and_creates_user(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $org = Organization::create([
            'name' => 'DLF Cybercity Developers',
            'status' => 'active',
        ]);

        $component = Livewire::actingAs($admin)
            ->test(OrganizationManager::class)
            ->call('openUserModal', $org->id)
            ->assertSet('showUserModal', true)
            ->assertSet('selectedOrgId', $org->id)
            ->assertSee('Manage Users — DLF Cybercity Developers');

        // Dynamic roles loaded from DB
        $availableRoles = $component->get('availableRoles');
        $this->assertIsArray($availableRoles);
        $this->assertNotEmpty($availableRoles);

        // Pick one role from dynamic available roles
        $roleToAssign = array_key_exists('channel-partner', $availableRoles) ? 'channel-partner' : (array_key_exists('Channel Partner', $availableRoles) ? 'Channel Partner' : array_key_first($availableRoles));

        $component->set('newUserName', 'Deepak Hooda')
            ->set('newUserEmail', 'deepak@dlf.com')
            ->set('newUserRole', $roleToAssign)
            ->call('createUserForOrganization')
            ->assertDispatched('toast');

        $this->assertDatabaseHas('users', [
            'name' => 'Deepak Hooda',
            'email' => 'deepak@dlf.com',
            'organization_id' => $org->id,
        ]);

        $createdUser = User::where('email', 'deepak@dlf.com')->first();
        $this->assertNotNull($createdUser);
        $this->assertTrue($createdUser->hasRole($roleToAssign));

        // Test unassigning user
        $component->call('removeUserFromOrganization', $createdUser->id)
            ->assertDispatched('toast');

        $this->assertDatabaseHas('users', [
            'id' => $createdUser->id,
            'organization_id' => null,
        ]);
    }

    public function test_organization_name_in_table_links_to_separate_detail_page(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $org = Organization::create([
            'name' => 'Brigade Group Enterprise',
            'status' => 'active',
        ]);

        Livewire::actingAs($admin)
            ->test(OrganizationManager::class)
            ->assertSee('Brigade Group Enterprise')
            ->assertSee(route('admin.organizations.show', $org->id), false);
    }

    public function test_super_admin_can_access_organization_detail_page_and_view_kpis_and_tabs(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $org = Organization::create([
            'name' => 'Godrej Properties HQ',
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.organizations.show', $org->id));
        $response->assertStatus(200);
        $response->assertSee('Godrej Properties HQ');
        $response->assertSee('Overview & Profile', false);
        $response->assertSee('Team Members');
        $response->assertSee('Projects', false);
        $response->assertSee('Activity & Audit', false);
    }

    public function test_organization_detail_component_updates_profile_and_toggles_status(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $org = Organization::create([
            'name' => 'Shapoorji Pallonji Real Estate',
            'status' => 'active',
        ]);

        Livewire::actingAs($admin)
            ->test(OrganizationDetail::class, ['organization' => $org])
            ->assertSee('Shapoorji Pallonji Real Estate')
            ->call('toggleOrganizationStatus')
            ->assertDispatched('toast');

        $this->assertDatabaseHas('organizations', [
            'id' => $org->id,
            'status' => 'suspended',
        ]);

        Livewire::actingAs($admin)
            ->test(OrganizationDetail::class, ['organization' => $org])
            ->call('startEditing')
            ->assertSet('isEditing', true)
            ->set('editName', 'Shapoorji Pallonji Joyville')
            ->set('editStatus', 'active')
            ->call('updateOrganization')
            ->assertSet('isEditing', false)
            ->assertDispatched('toast');

        $this->assertDatabaseHas('organizations', [
            'id' => $org->id,
            'name' => 'Shapoorji Pallonji Joyville',
            'status' => 'active',
        ]);
    }

    public function test_organization_detail_component_creates_member_and_client(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $org = Organization::create([
            'name' => 'Hiranandani Communities',
            'status' => 'active',
        ]);

        $component = Livewire::actingAs($admin)
            ->test(OrganizationDetail::class, ['organization' => $org]);

        // Add Team Member
        $component->call('openAddMemberModal')
            ->assertSet('showAddMemberModal', true)
            ->set('newUserName', 'Vikram Hiranandani')
            ->set('newUserEmail', 'vikram@hiranandani.com')
            ->set('newUserRole', 'Builder')
            ->call('createUserForOrganization')
            ->assertDispatched('toast');

        $this->assertDatabaseHas('users', [
            'name' => 'Vikram Hiranandani',
            'email' => 'vikram@hiranandani.com',
            'organization_id' => $org->id,
        ]);

        // Add Client
        $component->call('openCreateClientModal')
            ->assertSet('showCreateClientModal', true)
            ->set('newClientName', 'Hiranandani Estate Thane')
            ->set('newClientBillingEmail', 'billing@hiranandani.com')
            ->set('newClientStatus', 'active')
            ->call('createClientForOrganization')
            ->assertDispatched('toast')
            ->assertSet('showCreateClientModal', false);

        $this->assertDatabaseHas('users', [
            'name' => 'Hiranandani Estate Thane',
            'organization_id' => $org->id,
            'status' => 'active',
        ]);
    }

    public function test_organization_detail_creates_and_reassigns_directly_assigned_lead(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $org = Organization::create([
            'name' => 'Prestige Estates Ltd',
            'status' => 'active',
        ]);

        $agent1 = User::factory()->create([
            'organization_id' => $org->id,
            'name' => 'Agent One',
        ]);
        $agent2 = User::factory()->create([
            'organization_id' => $org->id,
            'name' => 'Agent Two',
        ]);

        $client = Client::create([
            'organization_id' => $org->id,
            'name' => 'Prestige Lakeside Habitat',
            'status' => 'active',
        ]);

        $project = Project::create([
            'client_id' => $client->id,
            'name' => 'Prestige Tower A',
            'status' => 'active',
        ]);

        $component = Livewire::actingAs($admin)
            ->test(OrganizationDetail::class, ['organization' => $org])
            ->call('setTab', 'leads')
            ->assertSet('activeTab', 'leads')
            ->call('openCreateLeadModal')
            ->assertSet('showCreateLeadModal', true)
            ->set('newLeadName', 'Anil Kumar')
            ->set('newLeadMobile', '+919988776655')
            ->set('newLeadEmail', 'anil@example.com')
            ->set('newLeadClientId', $client->id)
            ->set('newLeadProjectId', $project->id)
            ->set('newLeadAssignedTo', $agent1->id)
            ->set('newLeadStatus', 'new')
            ->set('newLeadRequirement', '3 BHK luxury apartment')
            ->call('createDirectlyAssignedLead')
            ->assertDispatched('toast')
            ->assertSet('showCreateLeadModal', false);

        $this->assertDatabaseHas('leads', [
            'name' => 'Anil Kumar',
            'mobile' => '+919988776655',
            'client_id' => $client->id,
            'project_id' => $project->id,
            'assigned_to' => $agent1->id,
        ]);

        $createdLead = Lead::where('mobile', '+919988776655')->first();
        $this->assertNotNull($createdLead);
        $this->assertEquals($agent1->id, $createdLead->assigned_to);

        // Reassign to Agent Two
        $component->call('reassignLead', $createdLead->id, $agent2->id)
            ->assertDispatched('toast');

        $this->assertDatabaseHas('leads', [
            'id' => $createdLead->id,
            'assigned_to' => $agent2->id,
        ]);
    }

    public function test_organization_detail_recharges_and_adjusts_client_credits(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $org = Organization::create([
            'name' => 'Godrej Properties HQ',
            'status' => 'active',
        ]);

        $client = Client::create([
            'organization_id' => $org->id,
            'name' => 'Godrej Prime Chembur',
            'status' => 'active',
        ]);

        $wallet = CreditWallet::create([
            'client_id' => $client->id,
            'balance' => 100.00,
        ]);

        $component = Livewire::actingAs($admin)
            ->test(OrganizationDetail::class, ['organization' => $org])
            ->call('setTab', 'credits')
            ->assertSet('activeTab', 'credits')
            ->call('openCreditModal', $client->id)
            ->assertSet('showCreditModal', true)
            ->set('selectedCreditClientId', $client->id)
            ->set('creditAdjustmentType', 'recharge')
            ->set('creditAmount', 250.00)
            ->set('creditReason', 'Payment received via RTGS')
            ->call('executeCreditAdjustment')
            ->assertDispatched('toast')
            ->assertSet('showCreditModal', false);

        $this->assertEquals(350.00, (float) CreditWallet::where('client_id', $client->id)->value('balance'));

        $this->assertDatabaseHas('credit_transactions', [
            'client_id' => $client->id,
            'transaction_type' => 'recharge',
            'credit_before' => 100.00,
            'credit_used' => 250.00,
            'credit_after' => 350.00,
        ]);
    }

    public function test_organization_detail_exports_full_multitab_excel_and_individual_tabs(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $org = Organization::create([
            'name' => 'Tata Realty Hub',
            'status' => 'active',
        ]);

        $client = Client::create([
            'organization_id' => $org->id,
            'name' => 'Tata Promont',
            'status' => 'active',
        ]);

        $component = Livewire::actingAs($admin)
            ->test(OrganizationDetail::class, ['organization' => $org]);

        // Full Multi-sheet Workbook Export
        $fullExport = $component->call('exportFullExcel');
        $this->assertNotNull($fullExport);

        // Tab-specific Exports
        $teamExport = $component->call('exportTabExcel', 'team');
        $this->assertNotNull($teamExport);

        $leadsExport = $component->call('exportTabExcel', 'leads');
        $this->assertNotNull($leadsExport);

        $creditsExport = $component->call('exportTabExcel', 'credits');
        $this->assertNotNull($creditsExport);

        $clientsExport = $component->call('exportTabExcel', 'clients');
        $this->assertNotNull($clientsExport);

        $activityExport = $component->call('exportTabExcel', 'activity');
        $this->assertNotNull($activityExport);
    }

    public function test_organization_detail_tab_in_url_and_pagination_goto_page(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $org = Organization::create([
            'name' => 'Brigade Horizon',
            'status' => 'active',
        ]);

        $client = Client::create([
            'organization_id' => $org->id,
            'name' => 'Brigade Horizon Client',
            'status' => 'active',
        ]);

        $project = Project::create([
            'client_id' => $client->id,
            'name' => 'Brigade Horizon Phase 1',
            'status' => 'active',
        ]);

        $source = LeadSource::first();

        // Create 20 leads to trigger multi-page pagination
        for ($i = 1; $i <= 20; $i++) {
            Lead::create([
                'lead_code' => "LP-BRIG-{$i}",
                'name' => "Lead {$i}",
                'mobile' => "+9198000000" . str_pad($i, 2, '0', STR_PAD_LEFT),
                'client_id' => $client->id,
                'project_id' => $project->id,
                'lead_source_id' => $source->id,
                'status' => 'new',
            ]);
        }

        // Test passing tab in query parameter
        $response = $this->actingAs($admin)->get(route('admin.organizations.show', ['organization' => $org->id, 'tab' => 'credits']));
        $response->assertStatus(200);

        // Test Livewire component respects tab and handles gotoPage() pagination without method not found exception
        Livewire::actingAs($admin)
            ->withQueryParams(['tab' => 'leads'])
            ->test(OrganizationDetail::class, ['organization' => $org])
            ->assertSet('activeTab', 'leads')
            ->call('gotoPage', 2)
            ->assertStatus(200)
            ->call('setTab', 'credits')
            ->assertSet('activeTab', 'credits');
    }
}


