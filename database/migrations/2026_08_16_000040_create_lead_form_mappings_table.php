<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('portal_accounts', 'health_status')) {
            Schema::table('portal_accounts', function (Blueprint $table) {
                $table->string('health_status')->default('untested')->after('status');
                $table->text('health_message')->nullable()->after('health_status');
            });
        }

        Schema::create('lead_form_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portal_account_id')->constrained('portal_accounts')->cascadeOnDelete();
            $table->string('form_id')->index();
            $table->string('form_name');
            $table->string('page_id')->nullable();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['portal_account_id', 'form_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_form_mappings');

        if (Schema::hasColumn('portal_accounts', 'health_status')) {
            Schema::table('portal_accounts', function (Blueprint $table) {
                $table->dropColumn(['health_status', 'health_message']);
            });
        }
    }
};
