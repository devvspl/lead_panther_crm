<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema, DB};

return new class extends Migration {
    public function up(): void
    {
        Schema::table('organizations', fn (Blueprint $t) => $t->unsignedInteger('executive_response_minutes')->default(15));
        Schema::table('leads', function (Blueprint $t) {
            $t->dateTime('executive_assigned_at')->nullable();
            $t->dateTime('executive_response_due_at')->nullable()->index();
            $t->dateTime('executive_first_response_at')->nullable();
        });
        Schema::table('lead_assignments', function (Blueprint $t) {
            $t->dateTime('response_due_at')->nullable();
            $t->dateTime('first_response_at')->nullable();
        });
        // Only known assignment timestamps are backfilled; never invent assignment times.
        DB::table('leads')->whereNotNull('assigned_to')->orderBy('id')->chunkById(200, function ($leads) {
            foreach ($leads as $lead) {
                $a = DB::table('lead_assignments')->where('lead_id', $lead->id)->where('assigned_to', $lead->assigned_to)->whereNull('unassigned_at')->latest('id')->first();
                if (!$a || !$a->assigned_at) continue;
                $start = \Carbon\Carbon::parse($a->assigned_at);
                $due = $start->copy()->addMinutes(15);
                $response = $lead->first_response_at && \Carbon\Carbon::parse($lead->first_response_at)->gte($start) ? $lead->first_response_at : null;
                DB::table('lead_assignments')->where('id', $a->id)->update(['response_due_at' => $due, 'first_response_at' => $response]);
                DB::table('leads')->where('id', $lead->id)->update(['executive_assigned_at' => $start, 'executive_response_due_at' => $due, 'executive_first_response_at' => $response]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('lead_assignments', fn (Blueprint $t) => $t->dropColumn(['response_due_at', 'first_response_at']));
        Schema::table('leads', function (Blueprint $t) {
            $t->dropIndex(['executive_response_due_at']);
            $t->dropColumn(['executive_assigned_at', 'executive_response_due_at', 'executive_first_response_at']);
        });
        Schema::table('organizations', fn (Blueprint $t) => $t->dropColumn('executive_response_minutes'));
    }
};
