<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('organizations', fn (Blueprint $table) => $table->string('delivery_timezone')->nullable());
        Schema::table('projects', fn (Blueprint $table) => $table->boolean('delivery_enabled')->default(true));
        Schema::table('leads', function (Blueprint $table) {
            $table->json('delivery_config')->nullable();
            $table->timestamp('delivery_failed_at')->nullable();
            $table->text('delivery_error')->nullable();
        });
        // Freeze existing queue settings without rewriting any scheduled timestamp.
        \Illuminate\Support\Facades\DB::table('leads')->where('delivery_pending', true)->whereNotNull('campaign_id')
            ->orderBy('id')->chunkById(200, function ($leads) {
                foreach ($leads as $lead) {
                    $campaign = \Illuminate\Support\Facades\DB::table('campaigns')->find($lead->campaign_id);
                    $project = $campaign ? \Illuminate\Support\Facades\DB::table('projects')->find($campaign->project_id) : null;
                    if (! $project) continue;
                    \Illuminate\Support\Facades\DB::table('leads')->where('id', $lead->id)->update(['delivery_config' => json_encode([
                        'limit' => (int) ($campaign->delivery_daily_limit ?? $project->delivery_daily_limit ?? 0),
                        'interval' => (int) ($campaign->delivery_interval_minutes ?? $project->delivery_interval_minutes ?? 0),
                        'timezone' => config('app.timezone'),
                    ])]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('leads', fn (Blueprint $table) => $table->dropColumn(['delivery_config', 'delivery_failed_at', 'delivery_error']));
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn('delivery_enabled'));
        Schema::table('organizations', fn (Blueprint $table) => $table->dropColumn('delivery_timezone'));
    }
};
