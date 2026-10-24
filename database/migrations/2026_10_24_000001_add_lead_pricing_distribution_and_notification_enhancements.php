<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Organization-level per-lead pricing
        if (Schema::hasTable('organizations') && !Schema::hasColumn('organizations', 'lead_cost')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->decimal('lead_cost', 10, 2)->nullable()->after('status')->comment('Per-lead cost override for this organization in credits');
            });
        }

        // 2. Team-level per-lead pricing
        if (Schema::hasTable('sales_teams') && !Schema::hasColumn('sales_teams', 'lead_cost')) {
            Schema::table('sales_teams', function (Blueprint $table) {
                $table->decimal('lead_cost', 10, 2)->nullable()->after('name')->comment('Per-lead cost override for this sales team in credits');
            });
        }

        // 3. Project-level per-lead pricing
        if (Schema::hasTable('projects') && !Schema::hasColumn('projects', 'lead_cost')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->decimal('lead_cost', 10, 2)->nullable()->after('status')->comment('Per-lead cost override for this project in credits');
            });
        }

        // 4. Lead actual charged cost
        if (Schema::hasTable('leads') && !Schema::hasColumn('leads', 'lead_cost')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->decimal('lead_cost', 10, 2)->nullable()->after('budget')->comment('Actual resolved credit cost charged for this lead');
            });
        }

        // 5. Opening balance on client wallets
        if (Schema::hasTable('client_wallets') && !Schema::hasColumn('client_wallets', 'opening_balance')) {
            Schema::table('client_wallets', function (Blueprint $table) {
                $table->decimal('opening_balance', 12, 2)->default(0.00)->after('balance')->comment('Opening credit balance baseline');
            });
        }

        // 6. Max capacity on team members for capacity-based distribution
        if (Schema::hasTable('sales_team_members') && !Schema::hasColumn('sales_team_members', 'max_capacity')) {
            Schema::table('sales_team_members', function (Blueprint $table) {
                $table->integer('max_capacity')->default(25)->after('is_active')->comment('Maximum active leads an agent can hold concurrently');
            });
        }

        // 7. Retries and error logging on notification_logs
        if (Schema::hasTable('notification_logs')) {
            Schema::table('notification_logs', function (Blueprint $table) {
                if (!Schema::hasColumn('notification_logs', 'retries')) {
                    $table->integer('retries')->default(0)->after('status');
                }
                if (!Schema::hasColumn('notification_logs', 'last_error')) {
                    $table->text('last_error')->nullable()->after('retries');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('organizations') && Schema::hasColumn('organizations', 'lead_cost')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->dropColumn('lead_cost');
            });
        }

        if (Schema::hasTable('sales_teams') && Schema::hasColumn('sales_teams', 'lead_cost')) {
            Schema::table('sales_teams', function (Blueprint $table) {
                $table->dropColumn('lead_cost');
            });
        }

        if (Schema::hasTable('projects') && Schema::hasColumn('projects', 'lead_cost')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->dropColumn('lead_cost');
            });
        }

        if (Schema::hasTable('leads') && Schema::hasColumn('leads', 'lead_cost')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->dropColumn('lead_cost');
            });
        }

        if (Schema::hasTable('client_wallets') && Schema::hasColumn('client_wallets', 'opening_balance')) {
            Schema::table('client_wallets', function (Blueprint $table) {
                $table->dropColumn('opening_balance');
            });
        }

        if (Schema::hasTable('sales_team_members') && Schema::hasColumn('sales_team_members', 'max_capacity')) {
            Schema::table('sales_team_members', function (Blueprint $table) {
                $table->dropColumn('max_capacity');
            });
        }

        if (Schema::hasTable('notification_logs')) {
            Schema::table('notification_logs', function (Blueprint $table) {
                if (Schema::hasColumn('notification_logs', 'retries')) {
                    $table->dropColumn('retries');
                }
                if (Schema::hasColumn('notification_logs', 'last_error')) {
                    $table->dropColumn('last_error');
                }
            });
        }
    }
};
