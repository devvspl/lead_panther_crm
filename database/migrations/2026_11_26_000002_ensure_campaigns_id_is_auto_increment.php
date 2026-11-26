<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ensure campaigns table primary key has auto-increment enabled
        if (Schema::hasTable('campaigns')) {
            $driver = DB::getDriverName();
            if ($driver === 'mysql') {
                $maxId = (int) DB::table('campaigns')->max('id');
                $nextId = max(1, $maxId + 1);
                DB::statement("ALTER TABLE campaigns MODIFY `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, AUTO_INCREMENT = {$nextId}");
            }
        }
    }

    public function down(): void
    {
        // No down action needed for auto_increment preservation
    }
};
