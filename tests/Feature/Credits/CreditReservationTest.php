<?php

namespace Tests\Feature\Credits;

use Tests\TestCase;
use App\Models\User;
use App\Models\Lead;
use App\Models\Organization;
use App\Models\Client;
use App\Models\Project;
use App\Models\LeadSource;
use App\Models\CreditWallet;
use App\Models\CreditTransaction;
use App\Events\CampaignPaused;
use App\Livewire\Credits\CreditWallet as CreditWalletComponent;
use App\Livewire\Admin\AdminCredits;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class CreditReservationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'super-admin']);
        Role::create(['name' => 'client']);
        Role::create(['name' => 'sales-executive']);
    }

    public function test_client_credit_wallet_view_renders_successfully(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        $response = $this->actingAs($user)->get('/credits');
        $response->assertOk();
    }

    public function test_credit_reservation_succeeds_when_wallet_has_positive_balance(): void
    {
        $org = Organization::create(['name' => 'Org 1', 'type' => 'builder']);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Client 1']);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Project 1']);
        $source = LeadSource::create(['name' => 'meta']);

        $wallet = CreditWallet::create([
            'client_id' => $client->id,
            'balance' => 100.00,
        ]);

        $lead = Lead::factory()->create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'lead_source_id' => $source->id,
        ]);

        $result = CreditTransaction::reserveForLead($lead);

        $this->assertTrue($result);
        $this->assertDatabaseHas('client_wallets', [
            'client_id' => $client->id,
            'balance' => 90.00,
        ]);

        $this->assertDatabaseHas('credit_transactions', [
            'client_id' => $client->id,
            'lead_id' => $lead->id,
            'transaction_type' => 'reserve',
            'credit_used' => 10.00,
        ]);
    }

    /**
     * CRITICAL RACE CONDITION TEST (Pessimistic Row Lock Verification):
     * Wallet balance is exactly 10.00 (enough for 1 lead). Two leads attempt credit reservation.
     * The pessimistic lock ensures only the 1st reservation succeeds, and the 2nd fails/holds.
     */
    public function test_concurrent_credit_reservation_race_condition_protection(): void
    {
        Event::fake([CampaignPaused::class]);

        $org = Organization::create(['name' => 'Org Lock', 'type' => 'builder']);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Client Lock']);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Project Lock']);
        $source = LeadSource::create(['name' => 'meta']);

        // Wallet has exactly 10.00 (cost of 1 lead)
        $wallet = CreditWallet::create([
            'client_id' => $client->id,
            'balance' => 10.00,
        ]);

        $lead1 = Lead::factory()->create(['client_id' => $client->id, 'project_id' => $project->id, 'lead_source_id' => $source->id]);
        $lead2 = Lead::factory()->create(['client_id' => $client->id, 'project_id' => $project->id, 'lead_source_id' => $source->id]);

        // First reservation attempt
        $res1 = CreditTransaction::reserveForLead($lead1);

        // Second reservation attempt
        $res2 = CreditTransaction::reserveForLead($lead2);

        // Assert 1st reservation succeeded and 2nd reservation failed
        $this->assertTrue($res1);
        $this->assertFalse($res2);

        // Wallet balance must be exactly 0.00 (not negative!)
        $this->assertEquals(0.00, (float) $wallet->fresh()->balance);

        // Lead 2 placed on pending_credit hold
        $this->assertDatabaseHas('leads', [
            'id' => $lead2->id,
            'current_stage' => 'pending_credit',
            'status' => 'pending_credit',
        ]);

        Event::assertDispatched(CampaignPaused::class);
    }

    public function test_credit_reservation_holds_lead_when_wallet_balance_is_zero(): void
    {
        Event::fake([CampaignPaused::class]);

        $org = Organization::create(['name' => 'Org 2', 'type' => 'builder']);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Client 2']);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Project 2']);
        $source = LeadSource::create(['name' => 'google']);

        $wallet = CreditWallet::create([
            'client_id' => $client->id,
            'balance' => 0.00,
        ]);

        $lead = Lead::factory()->create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'lead_source_id' => $source->id,
        ]);

        $result = CreditTransaction::reserveForLead($lead);

        $this->assertFalse($result);

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'current_stage' => 'pending_credit',
            'status' => 'pending_credit',
        ]);

        Event::assertDispatched(CampaignPaused::class);
    }

    public function test_admin_manual_balance_adjustment_writes_to_audit_logs(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $org = Organization::create(['name' => 'Org 3', 'type' => 'builder']);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Client 3']);
        CreditWallet::create(['client_id' => $client->id, 'balance' => 50.00]);

        Livewire::actingAs($admin)
            ->test(AdminCredits::class)
            ->set('selectedClientId', $client->id)
            ->set('adjustmentType', 'credit')
            ->set('adjustmentAmount', 200.00)
            ->set('reason', 'Manual test credit grant for client onboarding')
            ->call('executeAdjustment');

        $this->assertDatabaseHas('client_wallets', [
            'client_id' => $client->id,
            'balance' => 250.00,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'credit.manual_adjustment',
        ]);
    }

    public function test_check_low_credit_balances_command(): void
    {
        $org = Organization::create(['name' => 'Org 4', 'type' => 'builder']);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Client 4']);
        CreditWallet::create(['client_id' => $client->id, 'balance' => 10.00]);

        $this->artisan('credits:check-low-balances')
            ->assertExitCode(0);

        $this->assertDatabaseHas('notification_logs', [
            'notifiable_id' => $client->id,
            'notifiable_type' => Client::class,
        ]);
    }

    public function test_credit_wallet_export_excel_with_date_range_filters_executes_without_error(): void
    {
        $org = Organization::create(['name' => 'Org Export', 'type' => 'builder']);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Client Export']);
        $user = User::factory()->create(['organization_id' => $org->id]);
        $user->assignRole('client');

        CreditWallet::create(['client_id' => $client->id, 'balance' => 100.00]);

        CreditTransaction::create([
            'client_id' => $client->id,
            'lead_id' => null,
            'credit_before' => 0.00,
            'credit_used' => 100.00,
            'credit_after' => 100.00,
            'transaction_type' => 'recharge',
            'created_at' => now()->format('Y-m-d H:i:s'),
        ]);

        \Maatwebsite\Excel\Facades\Excel::fake();

        // Default 'All Time' export
        Livewire::actingAs($user)
            ->test(CreditWalletComponent::class)
            ->call('exportExcel');

        // Export with 'today' date range filter
        Livewire::actingAs($user)
            ->test(CreditWalletComponent::class)
            ->set('filterDateRange', 'today')
            ->set('filterType', 'recharge')
            ->call('exportExcel');

        // Export with 'week' date range filter
        Livewire::actingAs($user)
            ->test(CreditWalletComponent::class)
            ->set('filterDateRange', 'week')
            ->call('exportExcel');

        \Maatwebsite\Excel\Facades\Excel::assertDownloaded("credit-transactions_client-export_" . now()->format('Y-m-d') . ".xlsx");
    }
}
