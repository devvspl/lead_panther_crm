<?php

namespace Tests\Feature\Settings;

use Tests\TestCase;
use App\Models\User;
use App\Models\Organization;
use App\Models\PortalAccount;
use App\Models\IntegrationCredential;
use App\Livewire\Team\PartnerTeamManager;
use App\Livewire\Team\BuilderTeamManager;
use App\Livewire\Settings\OrganizationProfile;
use App\Livewire\Settings\IntegrationsManager;
use App\Livewire\Settings\UserInvite;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

class OrgSettingsAndSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'Super Admin']);
        Role::create(['name' => 'Builder']);
        Role::create(['name' => 'Channel Partner']);
        Role::create(['name' => 'Sales Executive']);
        Role::create(['name' => 'Account Manager']);
    }

    public function test_partner_and_builder_team_managers(): void
    {
        $partnerUser = User::factory()->create();
        $partnerUser->assignRole('Channel Partner');

        Livewire::actingAs($partnerUser)
            ->test(PartnerTeamManager::class)
            ->set('memberName', 'Partner Exec')
            ->set('memberEmail', 'exec@partner.com')
            ->set('mobile', '+919988776655')
            ->call('addTeamMember');

        $this->assertDatabaseHas('users', ['email' => 'exec@partner.com']);

        $builderUser = User::factory()->create();
        $builderUser->assignRole('Builder');

        Livewire::actingAs($builderUser)
            ->test(BuilderTeamManager::class)
            ->set('memberName', 'Builder Exec')
            ->set('memberEmail', 'exec@builder.com')
            ->set('roleName', 'Sales Executive')
            ->call('addMember');

        $this->assertDatabaseHas('users', ['email' => 'exec@builder.com']);
    }

    public function test_organization_profile_saves_name(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Builder');

        $org = Organization::create(['name' => 'Old Name', 'type' => 'builder']);
        $user->update(['organization_id' => $org->id]);

        Livewire::actingAs($user)
            ->test(OrganizationProfile::class)
            ->set('name', 'New Developer Org')
            ->call('save');

        $this->assertEquals('New Developer Org', $org->fresh()->name);
    }

    public function test_integrations_manager_encrypts_credentials_at_rest(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $secretToken = 'secret-api-token-12345';

        Livewire::actingAs($admin)
            ->test(IntegrationsManager::class)
            ->set('accountName', 'Meta Campaign Acc')
            ->set('portalType', 'meta')
            ->set('credentialKey', 'access_token')
            ->set('apiSecret', $secretToken)
            ->call('addCredential');

        $cred = IntegrationCredential::first();
        $this->assertNotNull($cred);

        // Verify decrypted value equals secret token
        $this->assertEquals($secretToken, $cred->encrypted_value);

        // Verify raw DB value is encrypted and not plain text
        $rawDbValue = \DB::table('integration_credentials')->where('id', $cred->id)->value('encrypted_value');
        $this->assertNotEquals($secretToken, $rawDbValue);
    }

    public function test_user_invite_generates_activation_link(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Builder');

        Livewire::actingAs($admin)
            ->test(UserInvite::class)
            ->set('name', 'Invited Member')
            ->set('email', 'invited@org.com')
            ->set('roleName', 'Sales Executive')
            ->call('inviteUser')
            ->assertSee('reset-password');

        $this->assertDatabaseHas('users', ['email' => 'invited@org.com']);
    }

    public function test_channel_partner_hitting_admin_route_returns_403_forbidden(): void
    {
        $partner = User::factory()->create();
        $partner->assignRole('Channel Partner');

        $response = $this->actingAs($partner)->get('/admin/dashboard');
        $response->assertStatus(403);
    }
}
