<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['projects', 'campaigns'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->unsignedInteger('delivery_daily_limit')->nullable();
                $table->unsignedInteger('delivery_interval_minutes')->nullable();
            });
        }
        Schema::table('leads', function (Blueprint $table) {
            $table->timestamp('delivery_at')->nullable()->index();
            $table->timestamp('delivered_at')->nullable()->index();
            $table->boolean('delivery_pending')->default(false)->index();
        });
    }

    public function down(): void
    {
        Schema::table('leads', fn (Blueprint $table) => $table->dropColumn(['delivery_at', 'delivered_at', 'delivery_pending']));
        foreach (['projects', 'campaigns'] as $table) {
            Schema::table($table, fn (Blueprint $table) => $table->dropColumn(['delivery_daily_limit', 'delivery_interval_minutes']));
        }
    }
};
