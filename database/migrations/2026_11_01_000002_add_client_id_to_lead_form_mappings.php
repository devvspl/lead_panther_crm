<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lead_form_mappings') && !Schema::hasColumn('lead_form_mappings', 'client_id')) {
            Schema::table('lead_form_mappings', function (Blueprint $table) {
                $table->unsignedBigInteger('client_id')->nullable()->after('page_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('lead_form_mappings') && Schema::hasColumn('lead_form_mappings', 'client_id')) {
            Schema::table('lead_form_mappings', function (Blueprint $table) {
                $table->dropColumn('client_id');
            });
        }
    }
};

