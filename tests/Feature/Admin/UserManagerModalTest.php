<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Organization;
use App\Livewire\Admin\UserManager;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

class UserManagerModalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_user_manager_renders_table_with_clickable_name_column(): void
    {
        $admin = User::where('email', 'admin@leadpanther.com')->first();
        if (!$admin) {
            $admin = User::factory()->create(['email' => 'admin@leadpanther.com']);
            $admin->assignRole('Super Admin');
        }

        $testUser = User::factory()->create([
            'name' => 'Vikram Malhotra',
            'email' => 'vikram@malhotra.com',
        ]);
        $testUser->assignRole('Sales Executive');

        Livewire::actingAs($admin)
            ->test(UserManager::class)
            ->assertSee('Global User Management')
            ->assertSee('Vikram Malhotra')
            ->assertSee('openUserDetailsModal(' . $testUser->id . ')');
    }

    public function test_open_user_details_modal_in_view_mode_and_switch_modes(): void
    {
        $admin = User::where('email', 'admin@leadpanther.com')->first();
        if (!$admin) {
            $admin = User::factory()->create();
            $admin->assignRole('Super Admin');
        }

        $org = Organization::firstOrCreate(['name' => 'Oberoi Realty', 'status' => 'active']);

        $user = User::factory()->create([
            'name' => 'Karan Singhania',
            'email' => 'karan@oberoi.com',
            'phone' => '+91 9988776655',
            'organization_id' => $org->id,
            'status' => 'active',
        ]);
        $user->assignRole('Channel Partner');

        Livewire::actingAs($admin)
            ->test(UserManager::class)
            ->call('openUserDetailsModal', $user->id)
            ->assertSet('showUserDetailsModal', true)
            ->assertSet('userDetailsMode', 'view')
            ->assertSet('selectedUserId', $user->id)
            ->assertSee('User Details')
            ->assertSee('Karan Singhania')
            ->assertSee('karan@oberoi.com')
            ->assertSee('+91 9988776655')
            ->assertSee('Oberoi Realty')
            ->assertSee('Channel Partner')
            ->call('switchUserDetailsMode', 'edit')
            ->assertSet('userDetailsMode', 'edit')
            ->assertSee('Edit User')
            ->assertSee('Save Changes')
            ->call('switchUserDetailsMode', 'view')
            ->assertSet('userDetailsMode', 'view')
            ->call('closeUserDetailsModal')
            ->assertSet('showUserDetailsModal', false)
            ->assertSet('selectedUserId', null);
    }

    public function test_update_user_details_saves_and_persists_changes(): void
    {
        $admin = User::where('email', 'admin@leadpanther.com')->first();
        if (!$admin) {
            $admin = User::factory()->create();
            $admin->assignRole('Super Admin');
        }

        $org1 = Organization::firstOrCreate(['name' => 'Puravankara Limited', 'status' => 'active']);
        $org2 = Organization::firstOrCreate(['name' => 'Sobha Realty', 'status' => 'active']);

        $user = User::factory()->create([
            'name' => 'Meera Deshmukh',
            'email' => 'meera@puravankara.com',
            'phone' => '+91 9123456780',
            'organization_id' => $org1->id,
            'status' => 'active',
        ]);
        $user->assignRole('Sales Executive');

        Livewire::actingAs($admin)
            ->test(UserManager::class)
            ->call('openUserDetailsModal', $user->id)
            ->call('switchUserDetailsMode', 'edit')
            ->set('editName', 'Meera D. Nair')
            ->set('editEmail', 'meera.nair@sobha.com')
            ->set('editPhone', '+91 9876500000')
            ->set('editOrganizationId', $org2->id)
            ->set('editRole', 'Account Manager')
            ->set('editStatus', 'suspended')
            ->call('updateUser')
            ->assertSet('userDetailsMode', 'view')
            ->assertDispatched('toast');

        $user->refresh();
        $this->assertEquals('Meera D. Nair', $user->name);
        $this->assertEquals('meera.nair@sobha.com', $user->email);
        $this->assertEquals('+91 9876500000', $user->phone);
        $this->assertEquals($org2->id, $user->organization_id);
        $this->assertEquals('suspended', $user->status);
        $this->assertTrue($user->hasRole('Account Manager'));
    }

    public function test_update_user_validation_rules(): void
    {
        $admin = User::where('email', 'admin@leadpanther.com')->first();
        if (!$admin) {
            $admin = User::factory()->create();
            $admin->assignRole('Super Admin');
        }

        $otherUser = User::factory()->create(['email' => 'existing@panther.com']);

        $user = User::factory()->create([
            'name' => 'Rajesh Gupta',
            'email' => 'rajesh@panther.com',
        ]);

        Livewire::actingAs($admin)
            ->test(UserManager::class)
            ->call('openUserDetailsModal', $user->id)
            ->call('switchUserDetailsMode', 'edit')
            ->set('editName', '')
            ->set('editEmail', 'not-an-email')
            ->call('updateUser')
            ->assertHasErrors(['editName' => 'required', 'editEmail' => 'email'])
            ->set('editEmail', 'existing@panther.com')
            ->call('updateUser')
            ->assertHasErrors(['editEmail' => 'unique']);
    }

    public function test_create_user_modal_replaces_offcanvas_and_creates_user(): void
    {
        $admin = User::where('email', 'admin@leadpanther.com')->first();
        if (!$admin) {
            $admin = User::factory()->create();
            $admin->assignRole('Super Admin');
        }

        $org = Organization::firstOrCreate(['name' => 'Godrej Properties', 'status' => 'active']);

        Livewire::actingAs($admin)
            ->test(UserManager::class)
            ->call('openCreateUserModal')
            ->assertSet('showCreateModal', true)
            ->assertSee('Create New User')
            ->set('newUserName', 'Sunil Narang')
            ->set('newUserEmail', 'sunil@godrej.com')
            ->set('newUserPhone', '+91 9811223344')
            ->set('newUserOrganizationId', $org->id)
            ->set('newUserRole', 'Builder')
            ->call('createUser')
            ->assertDispatched('toast');

        $this->assertDatabaseHas('users', [
            'name' => 'Sunil Narang',
            'email' => 'sunil@godrej.com',
            'phone' => '+91 9811223344',
            'organization_id' => $org->id,
            'status' => 'active',
        ]);

        $created = User::where('email', 'sunil@godrej.com')->first();
        $this->assertNotNull($created);
        $this->assertTrue($created->hasRole('Builder'));
    }

    public function test_create_user_modal_can_be_cancelled(): void
    {
        $admin = User::where('email', 'admin@leadpanther.com')->first();
        if (!$admin) {
            $admin = User::factory()->create();
            $admin->assignRole('Super Admin');
        }

        Livewire::actingAs($admin)
            ->test(UserManager::class)
            ->call('openCreateUserModal')
            ->assertSet('showCreateModal', true)
            ->set('newUserName', 'Draft User')
            ->call('closeCreateUserModal')
            ->assertSet('showCreateModal', false)
            ->assertSet('newUserName', '');
    }
}
