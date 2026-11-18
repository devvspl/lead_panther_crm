<?php
namespace Tests\Feature\Team;

use App\Livewire\Admin\OrganizationMemberDetails;
use App\Models\{Organization, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Hash};
use Livewire\Livewire;
use Tests\TestCase;

class OrganizationMemberDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_edit_and_change_password_only_for_this_organization(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $org = Organization::create(['name' => 'Selected client']);
        $admin = User::factory()->create(['status' => 'active']); $admin->assignRole('Super Admin');
        $member = User::factory()->create(['organization_id' => $org->id]); $member->assignRole('Executive');
        $other = User::factory()->create();
        $component = Livewire::actingAs($admin)->test(OrganizationMemberDetails::class, ['organizationId' => $org->id])
            ->dispatch('open-org-member', memberId: $member->id)->assertSee($member->email)->assertSee('Executive')
            ->set('editing', true)->set('name', 'Updated executive')->call('saveDetails')->assertHasNoErrors();
        $this->assertSame('Updated executive', $member->fresh()->name);
        $original = $member->password;
        $component->set('password', 'StrongNewPass!123')->set('password_confirmation', 'different')
            ->call('changePassword')->assertHasErrors('password');
        $this->assertSame($original, $member->fresh()->password);
        $component->set('password_confirmation', 'StrongNewPass!123')->call('changePassword')->assertHasNoErrors()
            ->assertSet('password', '')->assertSet('password_confirmation', '');
        $this->assertTrue(Hash::check('StrongNewPass!123', $member->fresh()->password));
        $this->assertStringNotContainsString('StrongNewPass', json_encode(DB::table('audit_logs')->get()));
        $component->call('open', $other->id)->assertNotFound();
        Livewire::actingAs($member)->test(OrganizationMemberDetails::class, ['organizationId' => $org->id])->assertForbidden();
    }
}
