<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->boolean('do_not_send_to_client')->default(false)->index();
            $table->timestamp('delivery_revoked_at')->nullable()->index();
            $table->text('delivery_revoke_reason')->nullable();
        });

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
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_delivery_revocations');

        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['do_not_send_to_client', 'delivery_revoked_at', 'delivery_revoke_reason']);
        });
    }
};
