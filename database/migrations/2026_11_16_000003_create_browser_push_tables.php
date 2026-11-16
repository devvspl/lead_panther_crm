<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('browser_push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->char('endpoint_hash', 64)->unique();
            $table->text('subscription');
            $table->timestamps();
        });
        Schema::create('browser_push_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('notification_id');
            $table->foreignId('subscription_id')->nullable()->constrained('browser_push_subscriptions')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['notification_id', 'subscription_id'], 'browser_push_delivery_unique');
        });
    }
    public function down(): void {
        Schema::dropIfExists('browser_push_deliveries');
        Schema::dropIfExists('browser_push_subscriptions');
    }
};
