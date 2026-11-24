<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('followups', function (Blueprint $table) {
            if (! Schema::hasColumn('followups', 'type')) {
                $table->string('type')->default('call')->after('due_at');
            }
            if (! Schema::hasColumn('followups', 'outcome')) {
                $table->string('outcome')->nullable()->after('note');
            }
            if (! Schema::hasColumn('followups', 'outcome_notes')) {
                $table->text('outcome_notes')->nullable()->after('outcome');
            }
            if (! Schema::hasColumn('followups', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('outcome_notes');
            }
            if (! Schema::hasColumn('followups', 'completed_by')) {
                $table->foreignId('completed_by')->nullable()->after('completed_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('followups', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('completed_by');
            }
            if (! Schema::hasColumn('followups', 'cancelled_by')) {
                $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('followups', 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable()->after('cancelled_by');
            }
            if (! Schema::hasColumn('followups', 'escalation_level')) {
                $table->unsignedTinyInteger('escalation_level')->default(0)->after('cancellation_reason');
            }
            if (! Schema::hasColumn('followups', 'escalated_at')) {
                $table->timestamp('escalated_at')->nullable()->after('escalation_level');
            }
            if (! Schema::hasColumn('followups', 'escalation_notes')) {
                $table->text('escalation_notes')->nullable()->after('escalated_at');
            }
        });

        Schema::table('followups', function (Blueprint $table) {
            $table->index(['status', 'due_at'], 'followups_status_due_at_index');
            $table->index(['escalation_level', 'due_at'], 'followups_escalation_due_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('followups', function (Blueprint $table) {
            $table->dropIndex('followups_status_due_at_index');
            $table->dropIndex('followups_escalation_due_at_index');
            $table->dropForeign(['completed_by']);
            $table->dropForeign(['cancelled_by']);
            $table->dropColumn([
                'type',
                'outcome',
                'outcome_notes',
                'completed_at',
                'completed_by',
                'cancelled_at',
                'cancelled_by',
                'cancellation_reason',
                'escalation_level',
                'escalated_at',
                'escalation_notes',
            ]);
        });
    }
};
