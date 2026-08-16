<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->index('name');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('name');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['name']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['name']);
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex(['name']);
        });
    }
};
