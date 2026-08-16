<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lead_sources') && !Schema::hasColumn('lead_sources', 'default_score_weight')) {
            Schema::table('lead_sources', function (Blueprint $table) {
                $table->integer('default_score_weight')->default(15)->after('name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('lead_sources') && Schema::hasColumn('lead_sources', 'default_score_weight')) {
            Schema::table('lead_sources', function (Blueprint $table) {
                $table->dropColumn('default_score_weight');
            });
        }
    }
};
