<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('followups', function (Blueprint $table) {
            if (! Schema::hasColumn('followups', 'assigned_to')) {
                $table->foreignId('assigned_to')->nullable()->after('lead_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('followups', 'assigned_by')) {
                $table->foreignId('assigned_by')->nullable()->after('assigned_to')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('followups', 'assigned_at')) {
                $table->timestamp('assigned_at')->nullable()->after('assigned_by');
            }
            if (! Schema::hasColumn('followups', 'requested_by')) {
                $table->foreignId('requested_by')->nullable()->after('assigned_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('followups', 'requested_at')) {
                $table->timestamp('requested_at')->nullable()->after('requested_by');
            }
            if (! Schema::hasColumn('followups', 'request_status')) {
                $table->string('request_status')->nullable()->after('requested_at'); // pending, accepted, in_progress, completed
            }
            if (! Schema::hasColumn('followups', 'acknowledged_at')) {
                $table->timestamp('acknowledged_at')->nullable()->after('request_status');
            }
        });

        Schema::table('followups', function (Blueprint $table) {
            $table->index(['assigned_to', 'status', 'due_at'], 'followups_assigned_status_due_index');
            $table->index(['requested_by', 'request_status'], 'followups_requested_by_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('followups', function (Blueprint $table) {
            $table->dropIndex('followups_assigned_status_due_index');
            $table->dropIndex('followups_requested_by_status_index');
            $table->dropForeign(['assigned_to']);
            $table->dropForeign(['assigned_by']);
            $table->dropForeign(['requested_by']);
            $table->dropColumn([
                'assigned_to',
                'assigned_by',
                'assigned_at',
                'requested_by',
                'requested_at',
                'request_status',
                'acknowledged_at',
            ]);
        });
    }
};
