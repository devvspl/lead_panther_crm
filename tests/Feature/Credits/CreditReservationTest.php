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

    public function test_integer_credit_migration_preserves_exact_legacy_values_without_spendable_conversion(): void
    {
        $previous = DB::getDefaultConnection();
        config(['database.connections.credit_migration_probe' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]]);
        DB::setDefaultConnection('credit_migration_probe');
        \Illuminate\Support\Facades\Schema::clearResolvedInstance('db.schema');
        try {
            $schema = DB::connection()->getSchemaBuilder();
            $schema->create('users', fn ($t) => $t->id());
            $schema->create('client_wallets', function ($t) { $t->id(); $t->decimal('balance', 15, 2); $t->decimal('opening_balance', 15, 2); });
            $schema->create('credit_transactions', function ($t) { $t->id(); foreach (['credit_before','credit_used','credit_after'] as $column) $t->decimal($column, 15, 2); });
            foreach (['organizations','sales_teams','projects','leads'] as $table) $schema->create($table, function ($t) use ($table) { $t->id(); $t->decimal('lead_cost', 10, 2)->nullable(); if ($table === 'organizations') $t->decimal('min_credit_threshold', 10, 2)->default(50); });
            $schema->create('credit_packages', function ($t) { $t->id(); $t->integer('credit_count'); $t->decimal('price', 15, 2); });
            $schema->create('recharge_requests', function ($t) { $t->id(); $t->unsignedBigInteger('package_id'); $t->decimal('amount', 15, 2); });
            DB::table('client_wallets')->insert(['balance'=>20000.75, 'opening_balance'=>20000.75]);
            DB::table('credit_transactions')->insert(['credit_before'=>20000.75, 'credit_used'=>25.5, 'credit_after'=>19975.25]);
            DB::table('projects')->insert(['lead_cost'=>2]);
            DB::table('leads')->insert(['lead_cost'=>25.5]);
            DB::table('credit_packages')->insert(['id'=>1, 'credit_count'=>100, 'price'=>5000]);
            DB::table('recharge_requests')->insert(['package_id'=>1, 'amount'=>5000]);
            $migration = require database_path('migrations/2026_11_21_100000_introduce_integer_lead_credits.php');
            $migration->up();
            $wallet = DB::table('client_wallets')->first();
            $this->assertEquals(20000.75, $wallet->legacy_balance);
            $this->assertEquals(0, $wallet->balance);
            $this->assertNull($wallet->reconciled_at);
            $tx = DB::table('credit_transactions')->first();
            $this->assertEquals(25.5, $tx->legacy_credit_used);
            $this->assertNull($tx->credit_used);
            $this->assertEquals(1, $tx->is_legacy);
            $this->assertEquals(2, DB::table('projects')->value('lead_cost'));
            $this->assertNull(DB::table('leads')->value('lead_cost'));
            $this->assertEquals(5000, DB::table('recharge_requests')->value('legacy_amount'));
            $this->assertEquals(100, DB::table('recharge_requests')->value('amount'));
        } finally {
            DB::setDefaultConnection($previous);
            DB::purge('credit_migration_probe');
            \Illuminate\Support\Facades\Schema::clearResolvedInstance('db.schema');
        }
    }

    private function leadCreditFixture(): array
    {
        $org = Organization::create(['name' => 'Integer credits', 'status' => 'active', 'lead_cost' => 2]);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Credit owner', 'status' => 'active']);
        Role::firstOrCreate(['name' => 'Builder/Developer']);
        User::findOrFail($client->id)->assignRole('Builder/Developer');
        $wallet = CreditWallet::create(['client_id' => $client->id, 'balance' => 10]);
        $source = LeadSource::create(['name' => 'Credit test source']);
        $lead = Lead::withoutEvents(fn () => Lead::factory()->create(['client_id' => $client->id, 'lead_source_id' => $source->id, 'status' => 'new', 'current_stage' => 'new',
            'delivery_pending' => true, 'delivery_at' => now(), 'delivered_at' => null, 'campaign_id' => null]));
        $admin = User::factory()->create(); $admin->assignRole('super-admin');
        return [$client, $wallet, $lead, $admin];
    }

    public function test_integer_credits_only_charge_successful_delivery_and_are_idempotent(): void
    {
        Event::fake([\App\Events\CreditReserved::class]);
        [$client, $wallet, $lead] = $this->leadCreditFixture();
        $this->assertFalse(CreditTransaction::reserveForLead($lead));
        $this->assertNull(app(\App\Services\LeadPricingService::class)->chargeForLead($lead));
        $this->assertSame(10, $wallet->fresh()->balance);
        $this->assertTrue(app(\App\Services\LeadDistributionService::class)->releaseDelivery($lead->id));
        $this->assertSame(8, $wallet->fresh()->balance);
        $this->assertSame(2, $wallet->fresh()->used_credits);
        $this->assertSame(10, $wallet->fresh()->total_credits);
        $this->assertTrue(CreditTransaction::reserveForLead($lead->fresh()));
        app(\App\Services\LeadDistributionService::class)->releaseDelivery($lead->id);
        $this->assertSame(1, CreditTransaction::where('lead_id', $lead->id)->count());
        $this->assertSame(8, $wallet->fresh()->balance);
    }

    public function test_integer_credits_roll_back_when_delivery_fails(): void
    {
        [$client, $wallet, $lead] = $this->leadCreditFixture();
        Event::listen(\App\Events\CreditReserved::class, fn () => throw new \RuntimeException('Simulated downstream failure'));
        $this->assertFalse(app(\App\Services\LeadDistributionService::class)->releaseDelivery($lead->id));
        $this->assertSame(10, $wallet->fresh()->balance);
        $this->assertNull($lead->fresh()->delivered_at);
        $this->assertSame(0, CreditTransaction::where('lead_id', $lead->id)->count());
    }

    public function test_integer_credits_reconciliation_preserves_dummy_history_and_requires_review(): void
    {
        [$client, $wallet, $lead, $admin] = $this->leadCreditFixture();
        DB::table('client_wallets')->where('id', $wallet->id)->update(['legacy_balance' => 20000, 'balance' => 0, 'reconciled_at' => null]);
        $legacyId = DB::table('credit_transactions')->insertGetId(['client_id' => $client->id, 'transaction_type' => 'recharge', 'is_legacy' => true,
            'legacy_credit_before' => 0, 'legacy_credit_used' => 20000, 'legacy_credit_after' => 20000, 'created_at' => now()]);
        $this->assertFalse(app(\App\Services\LeadDistributionService::class)->releaseDelivery($lead->id));
        $service = app(\App\Services\LeadCreditService::class);
        $service->reconcile($client, 1000, 0, 'Verified allocation replacing dummy setup', $admin);
        $this->assertSame(1000, $wallet->fresh()->balance);
        $this->assertNotNull($wallet->fresh()->reconciled_at);
        $this->assertEquals(20000, DB::table('client_wallets')->where('id', $wallet->id)->value('legacy_balance'));
        $this->assertEquals(20000, DB::table('credit_transactions')->where('id', $legacyId)->value('legacy_credit_used'));
        $this->assertNull(CreditTransaction::find($legacyId)->credit_used);
        $this->assertArrayNotHasKey('legacy_balance', $wallet->fresh()->toArray());
        $this->assertDatabaseHas('audit_logs', ['action' => 'lead_credits.reconciled']);
    }

    public function test_integer_credits_adjustments_reject_fractions_and_insufficient_credits(): void
    {
        [$client, $wallet, $lead, $admin] = $this->leadCreditFixture();
        $service = app(\App\Services\LeadCreditService::class);
        foreach ([['1.5', 'recharge'], [11, 'deduct']] as [$quantity, $action]) {
            try { $service->adjust($client, $quantity, $action, 'Test credit correction', $admin); $this->fail('Invalid quantity must be rejected'); }
            catch (\Illuminate\Validation\ValidationException $e) { $this->assertArrayHasKey('quantity', $e->errors()); }
        }
        $this->assertSame(10, $wallet->fresh()->balance);
        $service->adjust($client, 5, 'deduct', 'Confirmed credit correction', $admin);
        $this->assertSame(5, $wallet->fresh()->balance);
        $this->assertSame(0, $wallet->fresh()->used_credits);
    }

    public function test_integer_credits_disputes_create_linked_corrections_once(): void
    {
        [$client, $wallet, $lead, $admin] = $this->leadCreditFixture();
        $service = app(\App\Services\LeadCreditService::class);
        $original = $service->adjust($client, 5, 'deduct', 'Disputed admin deduction', $admin);
        $dispute = $service->dispute($original, 'Deduction was entered twice', $admin);
        $this->assertSame($dispute->id, $service->dispute($original, 'Repeated request', $admin)->id);
        $service->resolveDispute($dispute->id, 'Reviewed and returned five credits', 5, 'recharge', $admin);
        $this->assertSame(10, $wallet->fresh()->balance);
        $this->assertNotNull($dispute->fresh()->adjustment_transaction_id);
        $this->assertSame(5, $original->fresh()->credit_after);
        try { $service->resolveDispute($dispute->id, 'Repeat resolution', 5, 'recharge', $admin); $this->fail('Must not correct twice'); }
        catch (\Illuminate\Validation\ValidationException) { $this->assertSame(10, $wallet->fresh()->balance); }
    }

    public function test_integer_credits_management_is_super_admin_only_and_client_scoped(): void
    {
        [$client, $wallet, $lead, $admin] = $this->leadCreditFixture();
        foreach (['client', 'Builder/Developer', 'Team Leader', 'sales-executive'] as $role) {
            Role::firstOrCreate(['name' => $role]);
            $user = User::factory()->create(); $user->assignRole($role);
            Livewire::actingAs($user)->test(\App\Livewire\Credits\Reconciliation::class)->assertForbidden();
            try { app(\App\Services\LeadCreditService::class)->adjust($client, 1, 'recharge', 'Unauthorized correction', $user); $this->fail('Forbidden action'); }
            catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
        }
        Livewire::actingAs($admin)->test(\App\Livewire\Credits\Reconciliation::class, ['organizationId' => $client->organization_id])
            ->set('clientId', $client->id)->set('remaining', 1000)->set('reason', 'Verified client credits')->set('confirmed', true)
            ->call('reconcile')->assertHasNoErrors()->assertSee('Verified lead credits saved');
        $this->assertSame(1000, $wallet->fresh()->balance);
    }

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
        $org = Organization::create(['name' => 'Org 1', 'type' => 'builder', 'lead_cost' => 10]);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Client 1']);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Project 1']);
        $source = LeadSource::create(['name' => 'meta']);

        $wallet = CreditWallet::create([
            'client_id' => $client->id,
            'balance' => 100.00,
        ]);

        $lead = Lead::factory()->create(['status' => 'new', 'current_stage' => 'new',
            'client_id' => $client->id,
            'project_id' => $project->id,
            'lead_source_id' => $source->id,
        ]);

        $result = app(\App\Services\LeadDistributionService::class)->releaseDelivery($lead->id);

        $this->assertTrue($result);
        $this->assertDatabaseHas('client_wallets', [
            'client_id' => $client->id,
            'balance' => 90.00,
        ]);

        $this->assertDatabaseHas('credit_transactions', [
            'client_id' => $client->id,
            'lead_id' => $lead->id,
            'transaction_type' => 'lead_consumption',
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

        $org = Organization::create(['name' => 'Org Lock', 'type' => 'builder', 'lead_cost' => 10]);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Client Lock']);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Project Lock']);
        $source = LeadSource::create(['name' => 'meta']);

        // Wallet has exactly 10.00 (cost of 1 lead)
        $wallet = CreditWallet::create([
            'client_id' => $client->id,
            'balance' => 10.00,
        ]);

        $lead1 = Lead::factory()->create(['status' => 'new', 'current_stage' => 'new', 'client_id' => $client->id, 'project_id' => $project->id, 'lead_source_id' => $source->id]);
        $lead2 = Lead::factory()->create(['status' => 'new', 'current_stage' => 'new', 'client_id' => $client->id, 'project_id' => $project->id, 'lead_source_id' => $source->id]);

        // First reservation attempt
        $res1 = app(\App\Services\LeadDistributionService::class)->releaseDelivery($lead1->id);

        // Second reservation attempt
        $res2 = app(\App\Services\LeadDistributionService::class)->releaseDelivery($lead2->id);

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

        $org = Organization::create(['name' => 'Org 2', 'type' => 'builder', 'lead_cost' => 10]);
        $client = Client::create(['organization_id' => $org->id, 'name' => 'Client 2']);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Project 2']);
        $source = LeadSource::create(['name' => 'google']);

        $wallet = CreditWallet::create([
            'client_id' => $client->id,
            'balance' => 0.00,
        ]);

        $lead = Lead::factory()->create(['status' => 'new', 'current_stage' => 'new',
            'client_id' => $client->id,
            'project_id' => $project->id,
            'lead_source_id' => $source->id,
        ]);

        $result = app(\App\Services\LeadDistributionService::class)->releaseDelivery($lead->id);

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
            'action' => 'lead_credits.recharge',
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
