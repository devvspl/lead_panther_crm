<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->uuid('email_notification_id')->nullable()->unique();
            $table->timestamp('sent_at')->nullable()->change();
        });
    }
    public function down(): void {
        // Keep sent_at nullable: pending/failed attempts have no successful send time.
        Schema::table('notification_logs', fn (Blueprint $table) => $table->dropUnique(['email_notification_id']));
        Schema::table('notification_logs', fn (Blueprint $table) => $table->dropColumn('email_notification_id'));
    }
};
