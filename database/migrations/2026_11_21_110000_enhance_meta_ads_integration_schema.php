<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('meta_ad_records')) {
            Schema::table('meta_ad_records', function (Blueprint $table) {
                if (!Schema::hasColumn('meta_ad_records', 'effective_status')) {
                    $table->string('effective_status', 50)->nullable()->after('status')->index();
                }
                if (!Schema::hasColumn('meta_ad_records', 'creative_id')) {
                    $table->string('creative_id', 100)->nullable()->after('adset_id');
                }
                if (!Schema::hasColumn('meta_ad_records', 'lead_form_id')) {
                    $table->string('lead_form_id', 100)->nullable()->after('creative_id')->index();
                }
                if (!Schema::hasColumn('meta_ad_records', 'insight_level')) {
                    $table->string('insight_level', 20)->nullable()->after('lead_form_id')->index();
                }
            });
        }

        if (Schema::hasTable('lead_form_mappings')) {
            Schema::table('lead_form_mappings', function (Blueprint $table) {
                if (!Schema::hasColumn('lead_form_mappings', 'questions')) {
                    $table->json('questions')->nullable()->after('status');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('meta_ad_records')) {
            Schema::table('meta_ad_records', function (Blueprint $table) {
                $columns = [];
                if (Schema::hasColumn('meta_ad_records', 'effective_status')) $columns[] = 'effective_status';
                if (Schema::hasColumn('meta_ad_records', 'creative_id')) $columns[] = 'creative_id';
                if (Schema::hasColumn('meta_ad_records', 'lead_form_id')) $columns[] = 'lead_form_id';
                if (Schema::hasColumn('meta_ad_records', 'insight_level')) $columns[] = 'insight_level';
                if (!empty($columns)) $table->dropColumn($columns);
            });
        }

        if (Schema::hasTable('lead_form_mappings') && Schema::hasColumn('lead_form_mappings', 'questions')) {
            Schema::table('lead_form_mappings', function (Blueprint $table) {
                $table->dropColumn('questions');
            });
        }
    }
};
