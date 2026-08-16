<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recharge_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('recharge_requests', 'reference_note')) {
                $table->text('reference_note')->nullable();
            }
            if (!Schema::hasColumn('recharge_requests', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('recharge_requests', function (Blueprint $table) {
            $table->dropColumn(['reference_note', 'rejection_reason']);
        });
    }
};
