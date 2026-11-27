<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach ([
            'rate_per_lead' => fn(Blueprint $table) => $table->decimal('rate_per_lead', 10, 2)->default(0)->after('attempts'),
            'billing_amount' => fn(Blueprint $table) => $table->decimal('billing_amount', 10, 2)->default(0)->after('rate_per_lead'),
            'revoked_at' => fn(Blueprint $table) => $table->timestamp('revoked_at')->nullable()->after('accepted_at'),
            'revocation_reason' => fn(Blueprint $table) => $table->text('revocation_reason')->nullable()->after('revoked_at'),
        ] as $col => $definition) {
            try {
                if (!Schema::hasColumn('lead_delivery_items', $col)) {
                    Schema::table('lead_delivery_items', function (Blueprint $table) use ($definition) {
                        $definition($table);
                    });
                }
            } catch (\Throwable $e) {
                // Column may already exist
            }
        }

        // Drop single lead_id unique constraint to permit multi-client delivery
        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS lead_delivery_items_lead_id_unique');
            DB::statement('DROP INDEX IF EXISTS credit_transactions_delivery_charge_lead_id_unique');
        } else {
            try {
                Schema::table('lead_delivery_items', function (Blueprint $table) {
                    $table->dropForeign(['lead_id']);
                });
            } catch (\Throwable $e) {
                // Ignore if foreign key already dropped or differently named
            }

            try {
                Schema::table('lead_delivery_items', function (Blueprint $table) {
                    $table->dropUnique('lead_delivery_items_lead_id_unique');
                });
            } catch (\Throwable $e) {
                // Ignore if unique index already dropped
            }

            try {
                Schema::table('lead_delivery_items', function (Blueprint $table) {
                    $table->foreign('lead_id')->references('id')->on('leads')->restrictOnDelete();
                });
            } catch (\Throwable $e) {
                // Ignore if foreign key already exists
            }

            if (Schema::hasTable('credit_transactions') && Schema::hasColumn('credit_transactions', 'delivery_charge_lead_id')) {
                try {
                    Schema::table('credit_transactions', function (Blueprint $table) {
                        $table->dropForeign(['delivery_charge_lead_id']);
                    });
                } catch (\Throwable $e) {
                    // Ignore
                }

                try {
                    Schema::table('credit_transactions', function (Blueprint $table) {
                        $table->dropUnique('credit_transactions_delivery_charge_lead_id_unique');
                    });
                } catch (\Throwable $e) {
                    // Ignore
                }

                try {
                    Schema::table('credit_transactions', function (Blueprint $table) {
                        $table->foreign('delivery_charge_lead_id')->references('id')->on('leads')->nullOnDelete();
                    });
                } catch (\Throwable $e) {
                    // Ignore
                }
            }
        }

        // Composite unique index ensures a lead cannot be delivered duplicate times to the same client
        try {
            Schema::table('lead_delivery_items', function (Blueprint $table) {
                $table->unique(['lead_id', 'client_id'], 'lead_delivery_lead_client_unique');
            });
        } catch (\Throwable $e) {
            // Ignore if index already exists
        }
    }

    public function down(): void
    {
        try {
            Schema::table('lead_delivery_items', function (Blueprint $table) {
                $table->dropUnique('lead_delivery_lead_client_unique');
            });
        } catch (\Throwable $e) {
            // Ignore
        }

        Schema::table('lead_delivery_items', function (Blueprint $table) {
            $dropCols = array_filter(
                ['rate_per_lead', 'billing_amount', 'revoked_at', 'revocation_reason'],
                fn($col) => Schema::hasColumn('lead_delivery_items', $col)
            );
            if (!empty($dropCols)) {
                $table->dropColumn($dropCols);
            }
        });

        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS lead_delivery_items_lead_id_unique ON lead_delivery_items(lead_id)');
        } else {
            try {
                Schema::table('lead_delivery_items', function (Blueprint $table) {
                    $table->dropForeign(['lead_id']);
                    $table->unique('lead_id', 'lead_delivery_items_lead_id_unique');
                    $table->foreign('lead_id')->references('id')->on('leads')->restrictOnDelete();
                });
            } catch (\Throwable $e) {
                // Ignore
            }
        }
    }
};
