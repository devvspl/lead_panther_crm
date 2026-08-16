<?php

namespace Tests\Feature\Credits;

use Tests\TestCase;
use App\Models\User;
use App\Models\Organization;
use App\Models\Client;
use App\Models\CreditPackage;
use App\Models\CreditWallet;
use App\Models\RechargeRequest;
use App\Livewire\Admin\RechargeApprovalQueue;
use App\Livewire\Credits\CreditWallet as CreditWalletComponent;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

class RechargeApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'Super Admin']);
        Role::create(['name' => 'Client']);
    }

    public function test_recharge_approval_queue_renders_and_approves_request(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $org = Organization::create(['name' => 'Org Recharge', 'type' => 'builder']);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Client Recharge']);
        $wallet = CreditWallet::create(['client_id' => $client->id, 'balance' => 100.00]);

        $package = CreditPackage::create([
            'name' => 'Growth Pack',
            'credit_count' => 500,
            'price' => 5000.00,
            'validity_days' => 30,
        ]);

        $rechargeReq = RechargeRequest::create([
            'client_id' => $client->id,
            'package_id' => $package->id,
            'amount' => 5000.00,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test(RechargeApprovalQueue::class)
            ->assertSee('Growth Pack')
            ->set('referenceNote', 'Bank Transfer #998877')
            ->call('approveRequest', $rechargeReq->id);

        // Assert wallet credited (100 -> 600)
        $this->assertEquals(600.00, (float) $wallet->fresh()->balance);

        // Assert recharge_requests status approved
        $this->assertDatabaseHas('recharge_requests', [
            'id' => $rechargeReq->id,
            'status' => 'approved',
            'reference_note' => 'Bank Transfer #998877',
        ]);

        // Assert credit transaction logged
        $this->assertDatabaseHas('credit_transactions', [
            'client_id' => $client->id,
            'transaction_type' => 'recharge',
            'credit_used' => 500.00,
            'credit_after' => 600.00,
        ]);

        // Assert audit log created
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'recharge.approved',
            'subject_id' => $rechargeReq->id,
        ]);
    }

    public function test_recharge_approval_queue_rejects_request(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $org = Organization::create(['name' => 'Org Reject', 'type' => 'builder']);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Client Reject']);

        $package = CreditPackage::create([
            'name' => 'Starter Pack',
            'credit_count' => 100,
            'price' => 1000.00,
            'validity_days' => 30,
        ]);

        $rechargeReq = RechargeRequest::create([
            'client_id' => $client->id,
            'package_id' => $package->id,
            'amount' => 1000.00,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test(RechargeApprovalQueue::class)
            ->set('rejectionReason', 'Invalid bank transaction reference code')
            ->call('rejectRequest', $rechargeReq->id);

        $this->assertDatabaseHas('recharge_requests', [
            'id' => $rechargeReq->id,
            'status' => 'rejected',
            'rejection_reason' => 'Invalid bank transaction reference code',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'recharge.rejected',
            'subject_id' => $rechargeReq->id,
        ]);
    }

    public function test_client_credits_wallet_renders_recharge_status_honestly(): void
    {
        $org = Organization::create(['name' => 'Org Status', 'type' => 'builder']);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Client Status']);
        $user = User::factory()->create(['organization_id' => $org->id]);
        $user->assignRole('Client');

        $package = CreditPackage::create([
            'name' => 'Pro Pack',
            'credit_count' => 200,
            'price' => 2000.00,
            'validity_days' => 30,
        ]);

        RechargeRequest::create([
            'client_id' => $client->id,
            'package_id' => $package->id,
            'amount' => 2000.00,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(CreditWalletComponent::class)
            ->assertSee('Pending Approval');
    }
}
