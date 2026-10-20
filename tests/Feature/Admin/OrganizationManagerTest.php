<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Organization;
use App\Livewire\Admin\OrganizationManager;
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
            ->assertSee('Organizations Management')
            ->assertSee('Register Organization')
            ->call('openRegisterOrgModal', 'create')
            ->assertSet('showCreateOrgModal', true)
            ->assertSet('orgModalTab', 'create')
            ->assertSee('Create Organization')
            ->assertSee('Import Organizations')
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
            ->assertSee('#ORG-' . str_pad($org->id, 4, '0', STR_PAD_LEFT))
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
}

