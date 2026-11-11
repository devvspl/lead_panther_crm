<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Enhance credit_transactions for complete ledger recording
        if (Schema::hasTable('credit_transactions')) {
            Schema::table('credit_transactions', function (Blueprint $table) {
                if (!Schema::hasColumn('credit_transactions', 'description')) {
                    $table->string('description')->nullable()->after('transaction_type');
                }
                if (!Schema::hasColumn('credit_transactions', 'created_by')) {
                    $table->foreignId('created_by')->nullable()->after('description')->constrained('users')->nullOnDelete();
                }
            });

            // Widen transaction_type enum to varchar(50) if using MySQL
            try {
                DB::statement("ALTER TABLE credit_transactions MODIFY COLUMN transaction_type VARCHAR(50) NOT NULL DEFAULT 'lead_consumption'");
            } catch (\Throwable $e) {
                // If SQLite or already altered
            }
        }

        // 2. Enhance organizations with distribution control settings
        if (Schema::hasTable('organizations')) {
            Schema::table('organizations', function (Blueprint $table) {
                if (!Schema::hasColumn('organizations', 'auto_assign_enabled')) {
                    $table->boolean('auto_assign_enabled')->default(true)->after('status')->comment('Whether leads are automatically assigned to team members');
                }
                if (!Schema::hasColumn('organizations', 'distribution_method')) {
                    $table->string('distribution_method', 50)->default('round_robin')->after('auto_assign_enabled')->comment('Distribution rule: round_robin, capacity, team, priority, manual');
                }
                if (!Schema::hasColumn('organizations', 'min_credit_threshold')) {
                    $table->decimal('min_credit_threshold', 10, 2)->default(50.00)->after('distribution_method')->comment('Threshold below which low-balance alert triggers');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('credit_transactions')) {
            Schema::table('credit_transactions', function (Blueprint $table) {
                if (Schema::hasColumn('credit_transactions', 'created_by')) {
                    $table->dropForeign(['created_by']);
                    $table->dropColumn('created_by');
                }
                if (Schema::hasColumn('credit_transactions', 'description')) {
                    $table->dropColumn('description');
                }
            });
        }

        if (Schema::hasTable('organizations')) {
            Schema::table('organizations', function (Blueprint $table) {
                if (Schema::hasColumn('organizations', 'min_credit_threshold')) {
                    $table->dropColumn('min_credit_threshold');
                }
                if (Schema::hasColumn('organizations', 'distribution_method')) {
                    $table->dropColumn('distribution_method');
                }
                if (Schema::hasColumn('organizations', 'auto_assign_enabled')) {
                    $table->dropColumn('auto_assign_enabled');
                }
            });
        }
    }
};
