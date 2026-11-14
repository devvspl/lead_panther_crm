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
        \Illuminate\Support\Facades\DB::table('audit_logs')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $before = json_decode($row->from_value ?? '', true);
                $after = json_decode($row->to_value ?? '', true);
                $org = (is_array($after) ? ($after['organization_id'] ?? null) : null)
                    ?? (is_array($before) ? ($before['organization_id'] ?? null) : null);
                if ($row->subject_type === \App\Models\Organization::class) $org = $row->subject_id;
                if ($org) \Illuminate\Support\Facades\DB::table('audit_logs')->where('id', $row->id)->update(['organization_id' => $org]);
            }
        });
    }
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['organization_id']);
            $table->dropColumn(['organization_id', 'context']);
        });
    }
};
