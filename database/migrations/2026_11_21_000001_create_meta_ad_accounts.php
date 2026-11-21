<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('meta_ad_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portal_account_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('ad_account_id')->unique();
            $table->text('access_token')->nullable();
            $table->string('business_id')->nullable();
            $table->string('business_name')->nullable();
            $table->string('connection_name')->nullable();
            $table->string('facebook_user_id')->nullable();
            $table->string('status')->default('untested')->index();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('retry_after')->nullable();
            $table->string('last_error')->nullable();
            $table->json('sync_state')->nullable();
            $table->timestamps();
        });
        Schema::create('meta_ad_account_pages', function (Blueprint $table) {
            $table->foreignId('meta_ad_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('portal_account_id')->constrained()->restrictOnDelete();
            $table->primary(['meta_ad_account_id', 'portal_account_id']);
        });
        // Remote objects stay separate from CRM campaigns and their existing routing.
        Schema::create('meta_ad_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meta_ad_account_id')->constrained()->cascadeOnDelete();
            $table->string('ad_account_id')->index();
            $table->string('kind', 20);
            $table->string('remote_id', 100);
            $table->string('name')->nullable();
            $table->string('campaign_id')->nullable()->index();
            $table->string('adset_id')->nullable();
            $table->string('status')->nullable();
            $table->date('report_date')->nullable();
            $table->foreignId('crm_campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $table->json('data');
            $table->timestamp('synced_at');
            $table->timestamps();
            $table->unique(['meta_ad_account_id', 'kind', 'remote_id'], 'meta_ad_record_identity');
        });
        // No existing Page credentials, forms, campaigns or mappings are rewritten.
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_ad_records');
        Schema::dropIfExists('meta_ad_account_pages');
        Schema::dropIfExists('meta_ad_accounts');
    }
};
