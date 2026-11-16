<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('seeder_runs',function(Blueprint $t) {
            $t->id(); $t->string('seeder_class')->index(); $t->string('status')->index();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->unsignedInteger('change_count')->default(0); $t->timestamp('started_at'); $t->timestamp('completed_at')->nullable();
            $t->timestamp('reverted_at')->nullable(); $t->foreignId('reverted_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('error')->nullable(); $t->timestamps();
        });
        Schema::create('seeder_run_changes',function(Blueprint $t) {
            $t->id(); $t->foreignId('seeder_run_id')->constrained()->cascadeOnDelete();
            $t->string('table_name'); $t->string('operation',16); $t->text('record_key');
            $t->longText('before_values')->nullable(); $t->longText('after_values')->nullable();
        });
    }
    public function down(): void { Schema::dropIfExists('seeder_run_changes'); Schema::dropIfExists('seeder_runs'); }
};
