<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        try {
            if (!Schema::hasColumn('leads', 'do_not_send_to_client')) {
                Schema::table('leads', function (Blueprint $table) {
                    $table->boolean('do_not_send_to_client')->default(false)->index();
                });
            }
        } catch (\Throwable $e) {
            // Already exists or index exists
        }

        try {
            if (!Schema::hasColumn('leads', 'delivery_revoked_at')) {
                Schema::table('leads', function (Blueprint $table) {
                    $table->timestamp('delivery_revoked_at')->nullable()->index();
                });
            }
        } catch (\Throwable $e) {
            // Already exists or index exists
        }

        try {
            if (!Schema::hasColumn('leads', 'delivery_revoke_reason')) {
                Schema::table('leads', function (Blueprint $table) {
                    $table->text('delivery_revoke_reason')->nullable();
                });
            }
        } catch (\Throwable $e) {
            // Already exists
        }

        if (!Schema::hasTable('lead_delivery_revocations')) {
            try {
                Schema::create('lead_delivery_revocations', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
                    $table->foreignId('delivery_item_id')->nullable()->constrained('lead_delivery_items')->nullOnDelete();
                    $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
                    $table->foreignId('client_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
                    $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
                    $table->timestamp('original_delivered_at')->nullable();
                    $table->timestamp('revoked_at')->index();
                    $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
                    $table->text('return_reason');
                    $table->string('previous_status')->nullable();
                    $table->string('new_status')->default('revoked');
                    $table->timestamps();

                    $table->index(['lead_id', 'revoked_at']);
                    $table->index(['organization_id', 'revoked_at']);
                });
            } catch (\Throwable $e) {
                // Already created
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_delivery_revocations');

        Schema::table('leads', function (Blueprint $table) {
            $columnsToDrop = array_filter(
                ['do_not_send_to_client', 'delivery_revoked_at', 'delivery_revoke_reason'],
                fn($col) => Schema::hasColumn('leads', $col)
            );
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
