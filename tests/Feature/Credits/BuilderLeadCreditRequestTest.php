<?php

namespace Tests\Feature\Credits;

use Tests\TestCase;
use App\Models\User;
use App\Models\Organization;
use App\Models\Client;
use App\Models\CreditWallet;
use App\Models\RechargeRequest;
use App\Models\CreditTransaction;
use App\Livewire\Dashboard\DashboardShell;
use App\Livewire\Admin\RechargeApprovalQueue;
use App\Livewire\Credits\CreditWallet as CreditWalletComponent;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

class BuilderLeadCreditRequestTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $builder;
    protected Organization $org;
    protected CreditWallet $wallet;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('Super Admin');
        Role::findOrCreate('Builder/Developer');
        Role::findOrCreate('Client');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');

        $this->org = Organization::create([
            'name' => 'Apex Realty Developers',
            'type' => 'builder',
            'status' => 'active',
            'show_builder_credit_balance' => true,
        ]);

        $this->builder = User::factory()->create([
            'organization_id' => $this->org->id,
            'name' => 'Apex Builder Admin',
            'email' => 'builder@apexrealty.test',
            'status' => 'active',
        ]);
        $this->builder->assignRole('Builder/Developer');

        $this->wallet = CreditWallet::create([
            'client_id' => $this->builder->id,
            'balance' => 250,
            'opening_balance' => 250,
            'reconciled_at' => now(),
        ]);
    }

    public function test_builder_dashboard_renders_request_credits_button_and_balances(): void
    {
        Livewire::actingAs($this->builder)
            ->test(DashboardShell::class, ['role' => 'builder'])
            ->assertSee('Available Lead Credits')
            ->assertSee('Request Lead Credits')
            ->assertSee('Available Credits:')
            ->assertSee('Total Credits:')
            ->assertSee('250')
            ->call('openBuilderRechargeModal')
            ->assertSet('showBuilderRechargeModal', true)
            ->assertSet('builderRechargeAmount', 1000);
    }

    public function test_builder_can_submit_credit_request_with_validation_and_duplicate_prevention(): void
    {
        $component = Livewire::actingAs($this->builder)
            ->test(DashboardShell::class, ['role' => 'builder'])
            ->call('openBuilderRechargeModal')
            ->set('builderRechargeAmount', 0)
            ->call('submitBuilderRechargeRequest')
            ->assertHasErrors(['builderRechargeAmount']);

        $this->assertEquals(0, RechargeRequest::count());

        // Valid submission
        $component->set('builderRechargeAmount', 750)
            ->set('builderRechargeNote', 'Urgent campaign credit top-up')
            ->call('submitBuilderRechargeRequest')
            ->assertHasNoErrors()
            ->assertSet('showBuilderRechargeModal', false);

        $this->assertDatabaseHas('recharge_requests', [
            'client_id' => $this->builder->id,
            'amount' => 750,
            'status' => 'pending',
            'reference_note' => 'Urgent campaign credit top-up',
        ]);

        // Duplicate submission prevention
        $component->call('openBuilderRechargeModal')
            ->set('builderRechargeAmount', 750)
            ->call('submitBuilderRechargeRequest')
            ->assertHasErrors(['builderRechargeAmount']);

        $this->assertEquals(1, RechargeRequest::count());
    }

    public function test_super_admin_approves_builder_credit_request_and_allocates_balance(): void
    {
        $req = RechargeRequest::create([
            'client_id' => $this->builder->id,
            'package_id' => null,
            'amount' => 500,
            'status' => 'pending',
            'reference_note' => 'Expansion credits',
            'requested_at' => now(),
        ]);

        Livewire::actingAs($this->admin)
            ->test(RechargeApprovalQueue::class)
            ->assertSee('Apex Builder Admin')
            ->assertSee('500 Credits')
            ->assertSee('250 Credits') // Current balance column
            ->assertSee('Pending')
            ->set('referenceNote', 'Approved top-up')
            ->call('approveRequest', $req->id);

        // Wallet balance updated: 250 + 500 = 750
        $this->assertEquals(750, $this->wallet->fresh()->balance);

        // Request marked approved
        $this->assertDatabaseHas('recharge_requests', [
            'id' => $req->id,
            'status' => 'approved',
            'approved_quantity' => 500,
            'reference_note' => 'Approved top-up',
        ]);

        // Credit transaction recorded
        $this->assertDatabaseHas('credit_transactions', [
            'client_id' => $this->builder->id,
            'transaction_type' => 'recharge',
            'credit_before' => 250,
            'credit_used' => 500,
            'credit_after' => 750,
        ]);

        // Notification logged
        $this->assertDatabaseHas('notification_logs', [
            'notifiable_id' => $this->builder->id,
            'channel' => 'crm',
        ]);
    }

    public function test_super_admin_rejects_builder_credit_request_without_allocating_credits(): void
    {
        $req = RechargeRequest::create([
            'client_id' => $this->builder->id,
            'package_id' => null,
            'amount' => 1200,
            'status' => 'pending',
            'reference_note' => 'Special batch',
            'requested_at' => now(),
        ]);

        Livewire::actingAs($this->admin)
            ->test(RechargeApprovalQueue::class)
            ->set('rejectionReason', 'Quota exceeded for this month')
            ->call('rejectRequest', $req->id);

        // Wallet balance untouched (still 250)
        $this->assertEquals(250, $this->wallet->fresh()->balance);

        // Request marked rejected
        $this->assertDatabaseHas('recharge_requests', [
            'id' => $req->id,
            'status' => 'rejected',
            'rejection_reason' => 'Quota exceeded for this month',
        ]);

        // No new credit transaction
        $this->assertDatabaseMissing('credit_transactions', [
            'client_id' => $this->builder->id,
            'credit_used' => 1200,
        ]);
    }

    public function test_builder_dashboard_and_wallet_display_submitted_credit_requests_and_status(): void
    {
        RechargeRequest::create([
            'client_id' => $this->builder->id,
            'package_id' => null,
            'amount' => 300,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        RechargeRequest::create([
            'client_id' => $this->builder->id,
            'package_id' => null,
            'amount' => 800,
            'approved_quantity' => 800,
            'status' => 'approved',
            'approved_at' => now(),
            'requested_at' => now()->subDay(),
        ]);

        RechargeRequest::create([
            'client_id' => $this->builder->id,
            'package_id' => null,
            'amount' => 450,
            'status' => 'rejected',
            'rejection_reason' => 'Budget cap',
            'requested_at' => now()->subDays(2),
        ]);

        // Test Builder Dashboard displays submitted requests
        Livewire::actingAs($this->builder)
            ->test(DashboardShell::class, ['role' => 'builder'])
            ->assertSee('Pending Approval')
            ->assertSee('Approved')
            ->assertSee('Rejected');

        // Test Credit Wallet component renders them
        Livewire::actingAs($this->builder)
            ->test(CreditWalletComponent::class)
            ->assertSee('Pending Approval')
            ->assertSee('Approved on')
            ->assertSee('Rejected: Budget cap');
    }
}
