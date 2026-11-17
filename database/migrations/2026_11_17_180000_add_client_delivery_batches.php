<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('lead_delivery_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->unique()->constrained('organizations')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('client_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->foreignId('campaign_id')->constrained('campaigns')->restrictOnDelete();
            $table->json('settings');
            $table->string('state')->default('active')->index();
            $table->timestamp('next_run_at')->nullable()->index();
            $table->timestamps();
        });
        Schema::create('lead_delivery_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('lead_delivery_plans')->restrictOnDelete();
            $table->string('status')->index();
            $table->json('settings');
            $table->unsignedInteger('total')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
        Schema::create('lead_delivery_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('lead_delivery_plans')->restrictOnDelete();
            $table->foreignId('run_id')->nullable()->constrained('lead_delivery_runs')->restrictOnDelete();
            $table->foreignId('lead_id')->unique()->constrained('leads')->restrictOnDelete();
            $table->foreignId('source_client_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('source_campaign_id')->nullable()->constrained('campaigns')->restrictOnDelete();
            $table->foreignId('client_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->foreignId('campaign_id')->constrained('campaigns')->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('failure_reason')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->index(['plan_id', 'status', 'id']);
            $table->index(['plan_id', 'sent_at']);
        });
        Schema::create('lead_delivery_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('lead_delivery_items')->restrictOnDelete();
            $table->foreignId('run_id')->constrained('lead_delivery_runs')->restrictOnDelete();
            $table->string('status');
            $table->text('failure_reason')->nullable();
            $table->timestamps();
        });
        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('delivery_plan_id')->nullable()->constrained('lead_delivery_plans')->restrictOnDelete();
            $table->timestamp('delivery_consent_at')->nullable();
            $table->foreignId('delivery_consent_by')->nullable()->constrained('users')->restrictOnDelete();
        });
    }
    public function down(): void
    {
        Schema::table('leads', fn (Blueprint $table) => $table->dropConstrainedForeignId('delivery_plan_id'));
        Schema::table('leads', function (Blueprint $table) { $table->dropConstrainedForeignId('delivery_consent_by'); $table->dropColumn('delivery_consent_at'); });
        Schema::dropIfExists('lead_delivery_attempts');
        Schema::dropIfExists('lead_delivery_items');
        Schema::dropIfExists('lead_delivery_runs');
        Schema::dropIfExists('lead_delivery_plans');
    }
};
