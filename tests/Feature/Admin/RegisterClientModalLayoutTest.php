<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\OrganizationManager;
use App\Models\{Organization, SalesTeam, SalesTeamMember, User, CreditWallet};
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class RegisterClientModalLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') throw new \LogicException('Isolated SQLite required.');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('Super Admin');
        $this->actingAs($admin);
    }

    private function modal()
    {
        return Livewire::test(OrganizationManager::class)->call('openRegisterOrgModal');
    }

    private function mainStep()
    {
        return $this->modal()->set('name', 'Wizard Client')->set('billingEmail', 'owner@example.test')
            ->set('autoSetup', true)->call('nextRegistrationStep')->assertSet('registrationStep', 2);
    }

    private function executiveStep()
    {
        return $this->mainStep()->call('nextRegistrationStep')->assertSet('registrationStep', 3)
            ->set('teamName', 'Client Sales')->set('teamLeaderName', 'Leader')->set('teamLeaderEmail', 'leader@example.test')
            ->call('nextRegistrationStep')->assertSet('registrationStep', 4);
    }

    public function test_regular_registration_skips_accounts_and_import_remains_accessible(): void
    {
        $modal = $this->modal()->assertSet('registrationStep', 1)->assertSee('Create Client')->assertSee('Import Clients');
        $modal->call('nextRegistrationStep')->assertHasErrors('name')->assertSet('registrationStep', 1);
        $modal->set('name', 'Standard Client')->set('billingEmail', 'billing@example.test')->call('nextRegistrationStep')
            ->assertSet('registrationStep', 5)->assertSee('Step 2 of 2')->assertSee('Create Organization');
        $this->assertStringNotContainsString('wire:model="mainAccountPhone"', $modal->html());
        $modal->call('previousRegistrationStep')->assertSet('registrationStep', 1)->call('nextRegistrationStep')
            ->call('createOrganization')->assertHasNoErrors()->assertSet('showCreateOrgModal', false);
        $org = Organization::where('name', 'Standard Client')->firstOrFail();
        $this->assertSame(1, User::where('organization_id', $org->id)->count());
        $this->assertSame(0, SalesTeam::where('ownable_id', $org->id)->count());
        $this->modal()->set('orgModalTab', 'import')->assertSee('Download Import Template')->assertSee('CSV (.csv)')->assertSee('Confirm & Import');
    }

    public function test_autofill_preserves_edits_and_never_copies_email_into_mobile(): void
    {
        $modal = $this->mainStep()->assertSet('mainAccountName', 'Wizard Client')->assertSet('mainAccountEmail', 'owner@example.test')
            ->assertSet('mainAccountPhone', '')->assertSet('mainAccountPassword', '');
        $this->assertMatchesRegularExpression('/type="tel"[^>]*wire:model="mainAccountPhone"[^>]*autocomplete="tel"/', $modal->html());
        $modal->set('mainAccountName', 'Custom Owner')->set('mainAccountEmail', 'custom@example.test')->set('mainAccountPhone', '+91 9811111111')
            ->call('previousRegistrationStep')->set('name', 'Renamed Organization')->set('billingEmail', 'billing-new@example.test')
            ->call('nextRegistrationStep')->assertSet('mainAccountName', 'Custom Owner')->assertSet('mainAccountEmail', 'custom@example.test')
            ->assertSet('mainAccountPhone', '+91 9811111111');
    }

    public function test_untouched_autofill_tracks_organization_changes_and_cancel_resets_wizard(): void
    {
        $this->mainStep()->call('previousRegistrationStep')->set('name', 'Changed Client')->set('billingEmail', 'changed@example.test')
            ->call('nextRegistrationStep')->assertSet('mainAccountName', 'Changed Client')->assertSet('mainAccountEmail', 'changed@example.test')
            ->call('closeRegisterOrgModal')->assertSet('registrationStep', 1)->assertSet('mainAccountNameEdited', false)
            ->call('openRegisterOrgModal')->assertSet('autoSetup', false)->assertSet('mainAccountName', '');
    }

    public function test_each_step_validates_before_advancing_and_returns_to_invalid_step_on_final_submit(): void
    {
        $modal = $this->mainStep()->set('mainAccountEmail', 'bad-email')->call('nextRegistrationStep')
            ->assertHasErrors('mainAccountEmail')->assertSet('registrationStep', 2)
            ->set('mainAccountEmail', 'owner@example.test')->call('nextRegistrationStep')->assertSet('registrationStep', 3)
            ->call('nextRegistrationStep')->assertHasErrors(['teamLeaderName','teamLeaderEmail'])->assertSet('registrationStep', 3)
            ->set('teamLeaderName', 'Leader')->set('teamLeaderEmail', 'leader@example.test')->call('nextRegistrationStep')
            ->assertSet('registrationStep', 4)->call('nextRegistrationStep')->assertHasErrors(['salesExecutives.0.name','salesExecutives.0.email']);
        $modal->set('salesExecutives.0.name', 'Executive')->set('salesExecutives.0.email', 'exec@example.test')->call('nextRegistrationStep')
            ->assertSet('registrationStep', 5)->set('mainAccountEmail', 'invalid')->call('createOrganization')
            ->assertHasErrors('mainAccountEmail')->assertSet('registrationStep', 2)->assertSet('showCreateOrgModal', true);
        $this->assertDatabaseCount('organizations', 0);
    }

    public function test_dynamic_executive_rows_can_be_added_removed_and_reviewed_without_password_disclosure(): void
    {
        $modal = $this->executiveStep()->set('salesExecutives.0.name', 'First Executive')->set('salesExecutives.0.email', 'first@example.test')
            ->set('salesExecutives.0.password', 'PrivatePass123!')->call('addSalesExecutiveRow')
            ->set('salesExecutives.1.name', 'Remove Me')->call('addSalesExecutiveRow')
            ->set('salesExecutives.2.name', 'Third Executive')->set('salesExecutives.2.email', 'third@example.test')
            ->call('removeSalesExecutiveRow', 1)->assertSet('salesExecutives.1.name', 'Third Executive')->call('nextRegistrationStep')
            ->assertSet('registrationStep', 5)->assertSee('First Executive')->assertSee('Third Executive')->assertSee('Provided (hidden)');
        $this->assertStringNotContainsString('PrivatePass123!', strip_tags($modal->html()));
        $modal->call('editRegistrationStep', 4)->assertSet('registrationStep', 4)->assertSet('salesExecutives.0.password', 'PrivatePass123!');
    }

    public function test_matching_billing_and_main_email_reuses_one_account_and_submits_once(): void
    {
        $modal = $this->executiveStep()->set('salesExecutives.0.name', 'Executive One')->set('salesExecutives.0.email', 'one@example.test')
            ->call('addSalesExecutiveRow')->set('salesExecutives.1.name', 'Executive Two')->set('salesExecutives.1.email', 'two@example.test')
            ->call('nextRegistrationStep')->assertSet('registrationStep', 5)->call('createOrganization');
        $this->assertEmpty($modal->instance()->getErrorBag()->all(), implode(' ', $modal->instance()->getErrorBag()->all()));
        $modal->assertSet('registrationComplete', true)->assertSet('showCreateOrgModal', false);
        $org = Organization::where('name', 'Wizard Client')->firstOrFail();
        $main = User::where('email', 'owner@example.test')->firstOrFail();
        $leader = User::where('email', 'leader@example.test')->firstOrFail();
        $team = SalesTeam::where('ownable_type', Organization::class)->where('ownable_id', $org->id)->firstOrFail();
        $this->assertSame(4, User::where('organization_id', $org->id)->count());
        $this->assertTrue($main->hasRole('Builder/Developer'));
        $this->assertTrue($leader->hasRole('Team Leader'));
        $this->assertSame($leader->id, $team->leader_id);
        $this->assertSame(3, SalesTeamMember::where('sales_team_id', $team->id)->count());
        $this->assertNotNull(CreditWallet::where('client_id', $main->id)->first());
        foreach (User::where('organization_id', $org->id)->get() as $user) $this->assertNotEmpty($user->password);
        $modal->call('createOrganization')->assertHasNoErrors();
        $this->assertSame(1, Organization::where('name', 'Wizard Client')->count());
        $this->assertSame(4, User::where('organization_id', $org->id)->count());
    }

    public function test_repeated_executive_emails_are_rejected_before_creation(): void
    {
        $this->executiveStep()->set('salesExecutives', [
            ['name' => 'First', 'email' => 'same@example.test', 'phone' => '', 'password' => ''],
            ['name' => 'Second', 'email' => 'SAME@example.test', 'phone' => '', 'password' => ''],
        ])->call('nextRegistrationStep')->assertHasErrors(['salesExecutives.0.email','salesExecutives.1.email'])->assertSet('registrationStep', 4);
        $this->assertDatabaseCount('organizations', 0);
    }

    public function test_submission_lock_prevents_concurrent_creation(): void
    {
        $modal = $this->modal()->set('name', 'Locked Client')->call('nextRegistrationStep');
        $key = 'register-client:'.auth()->id().':'.$modal->get('registrationToken');
        $lock = Cache::lock($key, 120); $this->assertTrue($lock->get());
        $modal->call('createOrganization');
        $this->assertDatabaseMissing('organizations', ['name' => 'Locked Client']);
        $lock->release();
        $modal->call('createOrganization')->assertHasNoErrors();
        $this->assertSame(1, Organization::where('name', 'Locked Client')->count());
    }

    public function test_export_isolated_wizard_preview_when_requested(): void
    {
        if (getenv('REGISTER_CLIENT_PREVIEW') !== '1') $this->markTestSkipped('Opt-in visual fixture export.');
        $modal = $this->mainStep();
        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8" ?>'.$modal->html());
        $node = (new \DOMXPath($doc))->query('//*[@aria-label="register organization modal"]')->item(0);
        $this->assertNotNull($node);
        $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
        $css = $manifest['resources/css/app.css']['file'];
        file_put_contents(public_path('__register-client-preview.html'), '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/build/'.$css.'"></head><body>'.$doc->saveHTML($node).'</body></html>');
    }
}
