<?php

namespace Tests\Feature\Settings;

use Tests\TestCase;
use App\Models\User;
use App\Models\PortalAccount;
use App\Models\IntegrationCredential;
use App\Livewire\Settings\IntegrationsManager;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

class IntegrationsPageRedesignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Super Admin']);
    }

    public function test_integrations_page_http_route_accessible(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $response = $this->actingAs($admin)->get('/settings/integrations');
        $response->assertStatus(200);
        $response->assertSee('API &amp; Webhook Integrations', false);
        $response->assertSee('Meta Ads (Facebook)');
        $response->assertSee('Git Repository');
    }

    public function test_displays_only_two_top_tabs(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        Livewire::actingAs($admin)
            ->test(IntegrationsManager::class)
            ->assertSee('Meta Ads (Facebook)')
            ->assertSee('Git Repository')
            ->assertDontSee('Property Portals')
            ->assertDontSee('Bulk CSV Upload')
            ->assertDontSee('Configured Portal Accounts');
    }

    public function test_loads_saved_meta_configuration_into_form_fields(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $account = PortalAccount::create([
            'name' => 'Luxury Homes Meta Account',
            'type' => 'meta',
            'status' => 'active',
            'health_status' => 'healthy',
        ]);

        $account->setCredential('page_id', '109283746501928');
        $account->setCredential('app_id', '987654321098765');
        $account->setCredential('app_secret', 'sec_meta_secret_123');
        $account->setCredential('access_token', 'token_page_access_999');
        $account->setCredential('verify_token', 'verify_token_custom_abc');

        Livewire::actingAs($admin)
            ->test(IntegrationsManager::class)
            ->assertSet('activeTab', 'meta')
            ->assertSet('accountName', 'Luxury Homes Meta Account')
            ->assertSet('metaPageId', '109283746501928')
            ->assertSet('metaAppId', '987654321098765')
            ->assertSet('metaAppSecret', 'sec_meta_secret_123')
            ->assertSet('metaAccessToken', 'token_page_access_999')
            ->assertSet('metaVerifyToken', 'verify_token_custom_abc')
            ->assertSee('Luxury Homes Meta Account')
            ->assertSee('109283746501928');
    }

    public function test_loads_saved_git_configuration_when_switching_to_git_tab(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $gitAccount = PortalAccount::create([
            'name' => 'Lead Panther Core Repo',
            'type' => 'git',
            'status' => 'active',
            'health_status' => 'healthy',
        ]);

        $gitAccount->setCredential('remote_url', 'https://github.com/leadpanther/crm_core.git');
        $gitAccount->setCredential('username', 'deploy-bot');
        $gitAccount->setCredential('access_token', 'ghp_secret_pat_token_777');
        $gitAccount->setCredential('default_branch', 'production');
        $gitAccount->setCredential('committer_name', 'Deployer Bot');
        $gitAccount->setCredential('committer_email', 'deploy@leadpanther.com');

        Livewire::actingAs($admin)
            ->test(IntegrationsManager::class)
            ->call('switchTab', 'git')
            ->assertSet('activeTab', 'git')
            ->assertSet('gitAccountName', 'Lead Panther Core Repo')
            ->assertSet('gitRemoteUrl', 'https://github.com/leadpanther/crm_core.git')
            ->assertSet('gitUsername', 'deploy-bot')
            ->assertSet('gitAccessToken', 'ghp_secret_pat_token_777')
            ->assertSet('gitDefaultBranch', 'production')
            ->assertSet('gitCommitterName', 'Deployer Bot')
            ->assertSet('gitCommitterEmail', 'deploy@leadpanther.com')
            ->assertSee('Lead Panther Core Repo')
            ->assertSee('https://github.com/leadpanther/crm_core.git');
    }

    public function test_switching_tabs_does_not_mix_facebook_and_git_values(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        PortalAccount::create([
            'name' => 'FB Lead Account',
            'type' => 'meta',
            'status' => 'active',
        ]);

        PortalAccount::create([
            'name' => 'Git Sync Account',
            'type' => 'git',
            'status' => 'active',
        ]);

        $test = Livewire::actingAs($admin)
            ->test(IntegrationsManager::class);

        $test->assertSet('activeTab', 'meta')
            ->assertSet('accountName', 'FB Lead Account');

        $test->call('switchTab', 'git')
            ->assertSet('activeTab', 'git')
            ->assertSet('gitAccountName', 'Git Sync Account');

        $test->call('switchTab', 'meta')
            ->assertSet('activeTab', 'meta')
            ->assertSet('accountName', 'FB Lead Account');
    }

    public function test_saving_meta_updates_existing_record_without_duplicates(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $account = PortalAccount::create([
            'name' => 'Original Meta Account',
            'type' => 'meta',
            'status' => 'active',
        ]);

        Livewire::actingAs($admin)
            ->test(IntegrationsManager::class)
            ->set('accountName', 'Updated Meta Account')
            ->set('metaPageId', '888777666555')
            ->set('metaAccessToken', 'token_updated_abc')
            ->call('saveAccount')
            ->assertDispatched('toast');

        $this->assertEquals(1, PortalAccount::where('type', 'meta')->count());
        $account->refresh();
        $this->assertEquals('Updated Meta Account', $account->name);
        $this->assertEquals('888777666555', $account->getCredential('page_id'));
    }

    public function test_saving_git_updates_existing_record_without_duplicates(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $account = PortalAccount::create([
            'name' => 'Original Git Account',
            'type' => 'git',
            'status' => 'active',
        ]);

        Livewire::actingAs($admin)
            ->test(IntegrationsManager::class)
            ->call('switchTab', 'git')
            ->set('gitAccountName', 'Updated Git Repo Name')
            ->set('gitRemoteUrl', 'https://github.com/myorg/crm.git')
            ->set('gitUsername', 'crm-admin')
            ->set('gitDefaultBranch', 'staging')
            ->call('saveAccount')
            ->assertDispatched('toast');

        $this->assertEquals(1, PortalAccount::where('type', 'git')->count());
        $account->refresh();
        $this->assertEquals('Updated Git Repo Name', $account->name);
        $this->assertEquals('https://github.com/myorg/crm.git', $account->getCredential('remote_url'));
        $this->assertEquals('staging', $account->getCredential('default_branch'));
    }

    public function test_disconnect_integration_resets_credentials(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $account = PortalAccount::create([
            'name' => 'Live Meta Connection',
            'type' => 'meta',
            'status' => 'active',
            'health_status' => 'healthy',
        ]);

        $account->setCredential('access_token', 'active_token_123');

        Livewire::actingAs($admin)
            ->test(IntegrationsManager::class)
            ->call('disconnectCurrentIntegration')
            ->assertDispatched('toast');

        $account->refresh();
        $this->assertEquals('inactive', $account->status);
        $this->assertEquals('untested', $account->health_status);
        $this->assertEquals(0, $account->credentials()->count());
    }

    public function test_controller_does_not_set_any_default_values_when_unconfigured(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        // Confirm database has 0 portal accounts
        $this->assertEquals(0, PortalAccount::count());

        $test = Livewire::actingAs($admin)
            ->test(IntegrationsManager::class);

        // Check Meta tab fields have no default values
        $test->assertSet('accountName', '')
            ->assertSet('metaPageId', '')
            ->assertSet('metaAppId', '')
            ->assertSet('metaAppSecret', '')
            ->assertSet('metaAccessToken', '')
            ->assertSet('metaVerifyToken', '')
            ->assertSet('metaLastSyncedTime', '')
            ->assertSet('metaHealthStatus', '')
            ->assertSee('Not Connected')
            ->assertDontSee('Last synced:');

        // Check Git tab fields have no default values
        $test->call('switchTab', 'git')
            ->assertSet('gitAccountName', '')
            ->assertSet('gitRemoteUrl', '')
            ->assertSet('gitUsername', '')
            ->assertSet('gitAccessToken', '')
            ->assertSet('gitDefaultBranch', '')
            ->assertSet('gitCommitterName', '')
            ->assertSet('gitCommitterEmail', '')
            ->assertSet('gitLastSyncedTime', '')
            ->assertSet('gitHealthStatus', '')
            ->assertSee('Not Connected')
            ->assertDontSee('Last synced:');

        // Confirm mounting and switching tabs did NOT secretly insert any accounts
        $this->assertEquals(0, PortalAccount::count());
    }
}

