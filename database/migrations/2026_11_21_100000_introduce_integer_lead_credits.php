<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        // Preserve exact historical values; no implicit exchange rate or rounding.
        Schema::table('client_wallets', function (Blueprint $t) {
            $t->renameColumn('balance', 'legacy_balance');
            $t->renameColumn('opening_balance', 'legacy_opening_balance');
        });
        Schema::table('client_wallets', function (Blueprint $t) {
            $t->unsignedBigInteger('balance')->default(0);
            $t->unsignedBigInteger('opening_balance')->default(0);
            $t->timestamp('reconciled_at')->nullable();
            $t->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();
        });
        foreach (['credit_before', 'credit_used', 'credit_after'] as $column) {
            Schema::table('credit_transactions', fn (Blueprint $t) => $t->renameColumn($column, 'legacy_'.$column));
            Schema::table('credit_transactions', function (Blueprint $t) use ($column) {
                $t->decimal('legacy_'.$column, 15, 2)->nullable()->change();
                $t->unsignedBigInteger($column)->nullable();
            });
        }
        Schema::table('credit_transactions', function (Blueprint $t) {
            $t->boolean('is_legacy')->default(false);
            $t->foreignId('delivery_charge_lead_id')->nullable()->unique()->constrained('leads')->nullOnDelete();
        });
        Schema::table('leads', fn (Blueprint $t) => $t->boolean('credit_delivery_confirmed')->default(false));
        DB::table('credit_transactions')->update(['is_legacy' => true]);
        foreach (['organizations', 'sales_teams', 'projects', 'leads'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->renameColumn('lead_cost', 'legacy_lead_cost'));
            Schema::table($table, fn (Blueprint $t) => $t->unsignedInteger('lead_cost')->nullable());
            if ($table === 'leads') continue; // Historical monetary charges are not lead-credit consumption.
            DB::table($table)->whereNotNull('legacy_lead_cost')->orderBy('id')->chunkById(200, function ($rows) use ($table) {
                foreach ($rows as $row) {
                    $value = (float) $row->legacy_lead_cost;
                    if ($value > 0 && floor($value) === $value) DB::table($table)->where('id', $row->id)->update(['lead_cost' => (int) $value]);
                }
            });
        }
        Schema::table('organizations', fn (Blueprint $t) => $t->renameColumn('min_credit_threshold', 'legacy_min_credit_threshold'));
        Schema::table('organizations', fn (Blueprint $t) => $t->unsignedInteger('min_credit_threshold')->default(50));
        DB::table('organizations')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $value = (float) $row->legacy_min_credit_threshold;
                if ($value >= 0 && floor($value) === $value) DB::table('organizations')->where('id', $row->id)->update(['min_credit_threshold' => (int) $value]);
            }
        });
        Schema::table('recharge_requests', fn (Blueprint $t) => $t->renameColumn('amount', 'legacy_amount'));
        Schema::table('recharge_requests', function (Blueprint $t) {
            $t->decimal('legacy_amount', 15, 2)->nullable()->change();
            $t->unsignedBigInteger('amount')->nullable()->comment('Requested lead-credit quantity; retained API name');
            $t->unsignedBigInteger('approved_quantity')->nullable();
        });
        // Package quantities were already integer entitlements. Monetary prices are retained only for history.
        DB::table('recharge_requests')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $quantity = DB::table('credit_packages')->where('id', $row->package_id)->value('credit_count');
                if ($quantity) DB::table('recharge_requests')->where('id', $row->id)->update(['amount' => (int) $quantity]);
            }
        });
        Schema::table('credit_packages', fn (Blueprint $t) => $t->renameColumn('price', 'legacy_price'));
        Schema::table('credit_packages', fn (Blueprint $t) => $t->decimal('legacy_price', 15, 2)->nullable()->change());
        Schema::create('credit_disputes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('credit_transaction_id')->constrained()->restrictOnDelete();
            $t->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $t->text('reason');
            $t->string('status')->default('open')->index();
            $t->text('resolution')->nullable();
            $t->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('resolved_at')->nullable();
            $t->foreignId('adjustment_transaction_id')->nullable()->constrained('credit_transactions')->restrictOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        // Financial history and reconciliation must never be destroyed by an automatic rollback.
        throw new RuntimeException('Lead-credit migration requires a reviewed restore from backup; automatic rollback is disabled.');
    }
};
