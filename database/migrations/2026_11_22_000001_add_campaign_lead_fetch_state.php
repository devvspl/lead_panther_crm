<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('meta_ad_records', fn (Blueprint $table) => $table->json('lead_fetch_state')->nullable());
    }

    public function down(): void
    {
        Schema::table('meta_ad_records', fn (Blueprint $table) => $table->dropColumn('lead_fetch_state'));
    }
};
