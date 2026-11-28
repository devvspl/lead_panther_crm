<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('recharge_requests') && Schema::hasColumn('recharge_requests', 'package_id')) {
            try {
                Schema::table('recharge_requests', function (Blueprint $table) {
                    $table->unsignedBigInteger('package_id')->nullable()->change();
                });
            } catch (\Throwable $e) {
                // Ignore if driver already handles or column is already nullable
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('recharge_requests') && Schema::hasColumn('recharge_requests', 'package_id')) {
            try {
                Schema::table('recharge_requests', function (Blueprint $table) {
                    $table->unsignedBigInteger('package_id')->nullable(false)->change();
                });
            } catch (\Throwable $e) {
            }
        }
    }
};
