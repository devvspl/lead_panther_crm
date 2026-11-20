<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('lead_form_mappings', fn (Blueprint $table) => $table->dateTime('meta_created_at')->nullable());
    }

    public function down(): void
    {
        Schema::table('lead_form_mappings', fn (Blueprint $table) => $table->dropColumn('meta_created_at'));
    }
};
