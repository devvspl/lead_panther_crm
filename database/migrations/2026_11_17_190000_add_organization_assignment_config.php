<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('organizations', fn (Blueprint $table) => $table->json('assignment_config')->nullable()); }
    public function down(): void { Schema::table('organizations', fn (Blueprint $table) => $table->dropColumn('assignment_config')); }
};
