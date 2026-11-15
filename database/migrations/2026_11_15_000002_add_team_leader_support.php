<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('sales_teams', fn(Blueprint $t) => $t->foreignId('leader_id')->nullable()->constrained('users')->nullOnDelete());
        (new \Database\Seeders\TeamLeaderRoleSeeder)->run();
    }
    public function down(): void { Schema::table('sales_teams', fn(Blueprint $t) => $t->dropConstrainedForeignId('leader_id')); }
};
