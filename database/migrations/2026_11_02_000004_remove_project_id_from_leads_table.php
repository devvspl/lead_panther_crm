<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('leads') && Schema::hasColumn('leads', 'project_id')) {
            Schema::table('leads', function (Blueprint $table) {
                try {
                    $table->dropForeign(['project_id']);
                } catch (\Throwable $e) {
                    // Ignore if FK constraint has alternate name or SQLite
                }
                $table->dropColumn('project_id');
            });
        }

        if (Schema::hasTable('campaigns')) {
            Schema::table('campaigns', function (Blueprint $table) {
                try {
                    if (Schema::hasColumn('campaigns', 'project_id')) {
                        $table->foreignId('project_id')->nullable()->change();
                    }
                    if (Schema::hasColumn('campaigns', 'lead_source_id')) {
                        $table->foreignId('lead_source_id')->nullable()->change();
                    }
                } catch (\Throwable $e) {
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('leads') && !Schema::hasColumn('leads', 'project_id')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            });
        }
    }
};
