<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('lead_delivery_plans', fn (Blueprint $table) => $table->unsignedBigInteger('campaign_id')->nullable()->change()); }
    public function down(): void {
        if (\Illuminate\Support\Facades\DB::table('lead_delivery_plans')->whereNull('campaign_id')->exists()) throw new \LogicException('Select campaigns for project-only delivery plans before rolling back.');
        Schema::table('lead_delivery_plans', fn (Blueprint $table) => $table->unsignedBigInteger('campaign_id')->nullable(false)->change());
    }
};
