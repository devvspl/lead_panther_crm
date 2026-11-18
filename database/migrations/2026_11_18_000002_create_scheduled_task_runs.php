<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('scheduled_task_runs',function(Blueprint $table) {
            $table->id(); $table->string('task_key')->index(); $table->string('label'); $table->string('expression'); $table->string('timezone');
            $table->string('origin')->index(); $table->string('status')->index();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable()->index(); $table->timestamp('finished_at')->nullable();
            $table->unsignedBigInteger('duration_ms')->nullable(); $table->integer('exit_code')->nullable();
            $table->text('command')->nullable(); $table->mediumText('output')->nullable(); $table->text('error')->nullable(); $table->timestamps();
            $table->index(['task_key','created_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('scheduled_task_runs'); }
};
