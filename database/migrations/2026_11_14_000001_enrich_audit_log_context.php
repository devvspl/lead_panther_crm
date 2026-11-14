<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->json('context')->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('audit_logs', fn (Blueprint $table) => $table->dropColumn(['organization_id', 'context']));
    }
};
