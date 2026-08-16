<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('replacement_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->boolean('is_eligible')->default(true);
            $table->boolean('requires_sla_check')->default(false);
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('replacement_reasons');
    }
};
