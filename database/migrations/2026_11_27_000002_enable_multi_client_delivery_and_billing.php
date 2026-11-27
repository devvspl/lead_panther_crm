<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('lead_delivery_items', 'rate_per_lead')) {
            Schema::table('lead_delivery_items', function (Blueprint $table) {
                $table->decimal('rate_per_lead', 10, 2)->default(0)->after('attempts');
                $table->decimal('billing_amount', 10, 2)->default(0)->after('rate_per_lead');
                $table->timestamp('revoked_at')->nullable()->after('accepted_at');
                $table->text('revocation_reason')->nullable()->after('revoked_at');
            });
        }

        // Drop single lead_id unique constraint to permit multi-client delivery
        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS lead_delivery_items_lead_id_unique');
            DB::statement('DROP INDEX IF EXISTS credit_transactions_delivery_charge_lead_id_unique');
        } else {
            Schema::table('lead_delivery_items', function (Blueprint $table) {
                $table->dropForeign(['lead_id']);
                $table->dropUnique('lead_delivery_items_lead_id_unique');
                $table->foreign('lead_id')->references('id')->on('leads')->restrictOnDelete();
            });

            if (Schema::hasTable('credit_transactions') && Schema::hasColumn('credit_transactions', 'delivery_charge_lead_id')) {
                Schema::table('credit_transactions', function (Blueprint $table) {
                    $table->dropForeign(['delivery_charge_lead_id']);
                    $table->dropUnique('credit_transactions_delivery_charge_lead_id_unique');
                    $table->foreign('delivery_charge_lead_id')->references('id')->on('leads')->nullOnDelete();
                });
            }
        }

        // Composite unique index ensures a lead cannot be delivered duplicate times to the same client
        Schema::table('lead_delivery_items', function (Blueprint $table) {
            $table->unique(['lead_id', 'client_id'], 'lead_delivery_lead_client_unique');
        });
    }

    public function down(): void
    {
        Schema::table('lead_delivery_items', function (Blueprint $table) {
            $table->dropUnique('lead_delivery_lead_client_unique');
            if (Schema::hasColumn('lead_delivery_items', 'rate_per_lead')) {
                $table->dropColumn(['rate_per_lead', 'billing_amount', 'revoked_at', 'revocation_reason']);
            }
        });

        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS lead_delivery_items_lead_id_unique ON lead_delivery_items(lead_id)');
        } else {
            Schema::table('lead_delivery_items', function (Blueprint $table) {
                $table->dropForeign(['lead_id']);
                $table->unique('lead_id', 'lead_delivery_items_lead_id_unique');
                $table->foreign('lead_id')->references('id')->on('leads')->restrictOnDelete();
            });
        }
    }
};
