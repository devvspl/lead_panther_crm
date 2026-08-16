<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('project_unit_id')->nullable()->constrained('project_units')->nullOnDelete();
            $table->timestamp('visit_date')->index();
            $table->foreignId('executive_id')->constrained('users')->cascadeOnDelete();
            $table->enum('attendance', ['attended', 'no_show'])->default('attended')->index();
            $table->string('outcome')->nullable()->index();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_visits');
    }
};
