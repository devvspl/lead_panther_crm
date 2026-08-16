<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('project_unit_id')->constrained('project_units')->cascadeOnDelete();
            $table->decimal('price', 15, 2);
            $table->decimal('discount', 15, 2)->default(0);
            $table->date('validity_date')->nullable();
            $table->text('terms')->nullable();
            $table->timestamp('sent_at');
            $table->timestamp('viewed_at')->nullable();
            $table->string('status')->default('sent')->index();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposals');
    }
};
