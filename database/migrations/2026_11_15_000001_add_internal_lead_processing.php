<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema, DB};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('internal_processing_statuses', function (Blueprint $table) {
            $table->string('code')->primary(); $table->string('label'); $table->unsignedInteger('sort_order');
            $table->boolean('is_system')->default(false); $table->boolean('terminal')->default(false);
            $table->boolean('can_forward')->default(false); $table->boolean('default_forward')->default(false);
        });
        foreach ([
            ['received', 'Received', true, false, false], ['assigned', 'Assigned', true, false, false],
            ['calling_pending', 'Calling Pending', true, false, false], ['contacted', 'Contacted', false, false, true],
            ['followup_required', 'Follow-up Required', false, false, true], ['interested', 'Interested', false, false, true],
            ['not_interested', 'Not Interested', false, true, false], ['invalid', 'Invalid', false, true, false],
            ['duplicate', 'Duplicate', false, true, false], ['verified', 'Verified', false, false, true],
            ['forwarded_to_client', 'Forwarded to Client', true, true, false], ['on_hold', 'On Hold', false, false, false],
        ] as $order => [$code, $label, $system, $terminal, $forwardable]) {
            DB::table('internal_processing_statuses')->insert(['code' => $code, 'label' => $label, 'sort_order' => $order,
                'is_system' => $system, 'terminal' => $terminal, 'can_forward' => $forwardable, 'default_forward' => $code === 'verified']);
        }
        Schema::table('organizations', function (Blueprint $table) {
            $table->boolean('internal_processing_enabled')->default(false);
            $table->foreignId('internal_processing_team_id')->nullable()->constrained('sales_teams')->nullOnDelete();
            $table->json('internal_processing_config')->nullable();
        });
        Schema::table('leads', fn (Blueprint $table) => $table->boolean('internal_processing_pending')->default(false)->index());
        Schema::create('internal_lead_processing', function (Blueprint $table) {
            $table->id(); $table->foreignId('lead_id')->unique()->constrained('leads')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->foreignId('sales_team_id')->nullable()->constrained('sales_teams')->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status_code'); $table->foreign('status_code')->references('code')->on('internal_processing_statuses');
            $table->string('calling_status')->nullable(); $table->string('verification_status')->default('pending');
            $table->unsignedInteger('call_attempts')->default(0); $table->text('calling_remarks')->nullable();
            $table->timestamp('last_contacted_at')->nullable(); $table->timestamp('next_followup_at')->nullable()->index();
            $table->timestamp('verified_at')->nullable(); $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handover_requested_at')->nullable(); $table->foreignId('handover_requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('forwarded_at')->nullable(); $table->foreignId('forwarded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('forwarding_error')->nullable(); $table->timestamps();
            $table->index(['organization_id', 'status_code']); $table->index(['assigned_user_id', 'forwarded_at']);
        });
        Schema::create('internal_lead_processing_events', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('internal_lead_processing_id');
            $table->foreign('internal_lead_processing_id', 'internal_processing_event_record_fk')->references('id')->on('internal_lead_processing')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action'); $table->json('details')->nullable(); $table->timestamp('created_at')->useCurrent();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('internal_lead_processing_events'); Schema::dropIfExists('internal_lead_processing');
        Schema::table('leads', function (Blueprint $table) { $table->dropIndex(['internal_processing_pending']); $table->dropColumn('internal_processing_pending'); });
        Schema::table('organizations', function (Blueprint $table) { $table->dropForeign(['internal_processing_team_id']); $table->dropColumn(['internal_processing_enabled', 'internal_processing_team_id', 'internal_processing_config']); });
        Schema::dropIfExists('internal_processing_statuses');
    }
};
