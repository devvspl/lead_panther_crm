<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('organizations') && !Schema::hasColumn('organizations', 'lead_assignment_mode')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->string('lead_assignment_mode', 20)->default('automatic')->after('auto_assign_enabled')->comment('Lead assignment mode: automatic or manual');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('organizations') && Schema::hasColumn('organizations', 'lead_assignment_mode')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->dropColumn('lead_assignment_mode');
            });
        }
    }
};
