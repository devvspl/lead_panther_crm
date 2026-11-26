<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('lead_filter_rules', function (Blueprint $table) {
            if (!Schema::hasColumn('lead_filter_rules', 'project_id')) {
                $table->foreignId('project_id')->nullable()->after('campaign_id')->constrained('projects')->nullOnDelete();
            }
            if (!Schema::hasColumn('lead_filter_rules', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('priority')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('lead_filter_rules', function (Blueprint $table) {
            if (Schema::hasColumn('lead_filter_rules', 'project_id')) {
                $table->dropConstrainedForeignId('project_id');
            }
            if (Schema::hasColumn('lead_filter_rules', 'created_by')) {
                $table->dropConstrainedForeignId('created_by');
            }
        });
    }
};
