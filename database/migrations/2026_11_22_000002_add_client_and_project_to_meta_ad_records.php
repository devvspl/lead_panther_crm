<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('meta_ad_records')) {
            Schema::table('meta_ad_records', function (Blueprint $table) {
                if (!Schema::hasColumn('meta_ad_records', 'client_id')) {
                    $table->foreignId('client_id')->nullable()->after('report_date')->constrained('users')->nullOnDelete();
                }
                if (!Schema::hasColumn('meta_ad_records', 'project_id')) {
                    $table->foreignId('project_id')->nullable()->after('client_id')->constrained('projects')->nullOnDelete();
                }
            });

            // Backfill client_id and project_id for records with existing crm_campaign_id
            $existing = DB::table('meta_ad_records')->whereNotNull('crm_campaign_id')->whereNull('project_id')->get();
            foreach ($existing as $rec) {
                $campaign = DB::table('campaigns')->where('id', $rec->crm_campaign_id)->first();
                if ($campaign) {
                    $project = DB::table('projects')->where('id', $campaign->project_id)->first();
                    DB::table('meta_ad_records')->where('id', $rec->id)->update([
                        'project_id' => $campaign->project_id,
                        'client_id' => $project?->client_id,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('meta_ad_records')) {
            Schema::table('meta_ad_records', function (Blueprint $table) {
                if (Schema::hasColumn('meta_ad_records', 'project_id')) {
                    $table->dropForeign(['project_id']);
                    $table->dropColumn('project_id');
                }
                if (Schema::hasColumn('meta_ad_records', 'client_id')) {
                    $table->dropForeign(['client_id']);
                    $table->dropColumn('client_id');
                }
            });
        }
    }
};
