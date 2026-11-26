<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Leads composite indexes for role and stage scoping
        Schema::table('leads', function (Blueprint $table) {
            $table->index(['assigned_to', 'current_stage'], 'leads_assigned_stage_idx');
            $table->index(['client_id', 'current_stage'], 'leads_client_stage_idx');
            $table->index(['archived_at', 'internal_processing_pending'], 'leads_archived_internal_idx');
        });

        // 2. Audit logs composite indexes for subject lookup and organization logs
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['subject_type', 'subject_id', 'id'], 'audit_logs_subject_composite_idx');
            $table->index(['organization_id', 'created_at'], 'audit_logs_org_created_idx');
        });

        // 3. Notifications personal index for unread user alerts
        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'notifications_notifiable_read_idx');
        });

        // 4. Followups composite index for lead-scoped pending queries
        Schema::table('followups', function (Blueprint $table) {
            $table->index(['lead_id', 'status'], 'followups_lead_status_idx');
        });

        // 5. Lead metadata composite index for rapid key lookup
        Schema::table('lead_metadata', function (Blueprint $table) {
            $table->index(['lead_id', 'key'], 'lead_metadata_lead_key_idx');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex('leads_assigned_stage_idx');
            $table->dropIndex('leads_client_stage_idx');
            $table->dropIndex('leads_archived_internal_idx');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_subject_composite_idx');
            $table->dropIndex('audit_logs_org_created_idx');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_notifiable_read_idx');
        });

        Schema::table('followups', function (Blueprint $table) {
            $table->dropIndex('followups_lead_status_idx');
        });

        Schema::table('lead_metadata', function (Blueprint $table) {
            $table->dropIndex('lead_metadata_lead_key_idx');
        });
    }
};
