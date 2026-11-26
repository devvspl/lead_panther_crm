<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('lead_filter_rules')) {
            Schema::create('lead_filter_rules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
                $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('action')->default('include'); // 'include' or 'exclude'
                $table->string('group_logic')->default('OR'); // 'AND' or 'OR' between groups
                $table->json('conditions'); // groups array with condition definitions
                $table->boolean('is_active')->default(true);
                $table->integer('priority')->default(0);
                $table->timestamps();

                $table->index(['organization_id', 'is_active']);
                $table->index(['campaign_id', 'is_active']);
            });
        }

        if (!Schema::hasTable('lead_filter_logs')) {
            Schema::create('lead_filter_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
                $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
                $table->foreignId('rule_id')->nullable()->constrained('lead_filter_rules')->nullOnDelete();
                $table->string('lead_identifier')->nullable();
                $table->string('action'); // 'skipped', 'included'
                $table->text('reason')->nullable();
                $table->json('matched_conditions')->nullable();
                $table->json('payload_snapshot')->nullable();
                $table->timestamp('created_at')->nullable()->useCurrent();

                $table->index(['organization_id', 'created_at']);
                $table->index(['campaign_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_filter_logs');
        Schema::dropIfExists('lead_filter_rules');
    }
};
