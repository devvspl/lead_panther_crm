<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->string('event_key', 64)->nullable()->unique();
            $table->index(['organization_id', 'notifiable_id', 'read_at', 'created_at'], 'notifications_inbox_index');
        });
        Schema::table('users', fn (Blueprint $table) => $table->json('notification_preferences')->nullable());
        DB::table('notifications')->orderBy('id')->chunkById(200, function ($items) {
            foreach ($items as $item) {
                if (! in_array($item->notifiable_type, [\App\Models\User::class, \App\Models\Client::class])) continue;
                $data = json_decode($item->data, true) ?: [];
                $leadId = $data['lead_id'] ?? null;
                if (! $leadId && isset($data['followup_id'])) $leadId = DB::table('followups')->where('id', $data['followup_id'])->value('lead_id');
                if (! $leadId && isset($data['replacement_id'])) $leadId = DB::table('lead_replacements')->where('id', $data['replacement_id'])->value('lead_id');
                $lead = $leadId ? DB::table('leads')->find($leadId) : null;
                $ownerId = $lead?->client_id ?? $data['client_id'] ?? null;
                // Derive the original source organization, never assume the recipient's org.
                // Unverifiable legacy messages stay quarantined from organization inboxes.
                $org = $ownerId ? DB::table('users')->where('id', $ownerId)->value('organization_id') : null;
                if (! $ownerId) $data['quarantined'] = true;
                $recipient = \App\Models\User::find($item->notifiable_id);
                if ($lead && $recipient && \App\Support\NavigationConfig::getRoleKey($recipient) === 'sales-executive' && $lead->assigned_to != $recipient->id) {
                    $org = null;
                    $data['quarantined'] = true;
                }
                if ($lead) { $data['entity_type'] = 'lead'; $data['entity_id'] = $lead->id; }
                DB::table('notifications')->where('id', $item->id)->update(['organization_id' => $org, 'data' => json_encode($data)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_inbox_index');
            $table->dropUnique(['event_key']);
            $table->dropColumn(['organization_id', 'event_key']);
        });
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('notification_preferences'));
    }
};
