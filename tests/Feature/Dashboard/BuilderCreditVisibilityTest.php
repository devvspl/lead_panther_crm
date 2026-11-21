<?php
namespace Tests\Feature\Dashboard;
use Tests\TestCase;
use App\Models\{User,Organization,CreditWallet};
use App\Livewire\Dashboard\DashboardShell;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
class BuilderCreditVisibilityTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); $this->seed(\Database\Seeders\RoleSeeder::class); }
    public function test_admin_controls_builder_credit_visibility_per_organization(): void
    {
        $org = Organization::create(['name' => 'Visibility client', 'status' => 'active']);
        $other = Organization::create(['name' => 'Other client', 'status' => 'active']);
        $builder = User::factory()->create(['organization_id' => $org->id, 'status' => 'active']);
        $builder->assignRole('Builder/Developer');
        CreditWallet::create(['client_id' => $builder->id, 'balance' => 250]);
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('Super Admin');
        Livewire::actingAs($admin)->test(\App\Livewire\Admin\OrganizationDetail::class, ['organization' => $org])
            ->assertSee('Show Credit Balance')
            ->call('setBuilderCreditBalanceVisibility', false)->assertHasNoErrors();
        $this->assertFalse($org->fresh()->show_builder_credit_balance);
        $this->assertTrue($other->fresh()->show_builder_credit_balance);
        $dashboard = Livewire::actingAs($builder)->test(DashboardShell::class, ['role' => 'builder'])
            ->assertDontSee('Credit Balance')->assertDontSee('Available Credits')->assertSee('Total Leads');
        $this->assertSame([], $dashboard->instance()->getBuilderDashboardDataProperty()['credits']);
        $this->actingAs($builder);
        $component = new \App\Livewire\Admin\OrganizationDetail;
        $component->organization = $org;
        try {
            $component->setBuilderCreditBalanceVisibility(true);
            $this->fail('Builders must not change this setting.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        Livewire::actingAs($admin)->test(\App\Livewire\Admin\OrganizationDetail::class, ['organization' => $org->fresh()])
            ->call('setBuilderCreditBalanceVisibility', true)->assertHasNoErrors();
        Livewire::actingAs($builder)->test(DashboardShell::class, ['role' => 'builder'])
            ->assertSee('Available Lead Credits')->assertSee('Available Credits');
        $this->assertDatabaseHas('audit_logs', ['organization_id' => $org->id, 'action' => 'organization.credit_balance_visibility_updated']);
    }

}
