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

        // Backfill creative_id and lead_form_id for existing ad records from JSON data
        if (Schema::hasTable('meta_ad_records')) {
            \Illuminate\Support\Facades\DB::table('meta_ad_records')
                ->where('kind', 'ad')
                ->whereNull('lead_form_id')
                ->orderBy('id')
                ->chunk(100, function ($ads) {
                    foreach ($ads as $ad) {
                        $row = json_decode($ad->data, true) ?: [];
                        $creativeId = data_get($row, 'creative.id');
                        $leadFormId = data_get($row, 'creative.lead_gen_form_id')
                            ?? data_get($row, 'creative.object_story_spec.link_data.call_to_action.value.lead_gen_form_id')
                            ?? data_get($row, 'creative.object_story_spec.video_data.call_to_action.value.lead_gen_form_id')
                            ?? data_get($row, 'creative.asset_feed_spec.link_urls.0.lead_gen_form_id');
                        if (!$leadFormId && !empty($row['creative']['asset_feed_spec']['link_urls'])) {
                            foreach ((array) $row['creative']['asset_feed_spec']['link_urls'] as $lUrl) {
                                if (!empty($lUrl['lead_gen_form_id'])) {
                                    $leadFormId = $lUrl['lead_gen_form_id'];
                                    break;
                                }
                            }
                        }
                        if ($leadFormId || $creativeId) {
                            \Illuminate\Support\Facades\DB::table('meta_ad_records')->where('id', $ad->id)->update([
                                'creative_id' => $creativeId ? (string) $creativeId : $ad->creative_id,
                                'lead_form_id' => $leadFormId ? (string) $leadFormId : $ad->lead_form_id,
                            ]);
                        }
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
