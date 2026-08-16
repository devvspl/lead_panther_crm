<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('package_id')->nullable()->constrained('credit_packages')->nullOnDelete();
            $table->decimal('credit_before', 15, 2);
            $table->decimal('credit_used', 15, 2);
            $table->decimal('credit_after', 15, 2);
            $table->enum('transaction_type', ['reserve', 'deduct', 'refund', 'recharge'])->default('deduct')->index();
            $table->timestamp('created_at')->index();

            $table->index(['client_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
    }
};
