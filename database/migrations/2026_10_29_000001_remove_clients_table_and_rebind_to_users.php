<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. If any clients exist, migrate them into the users table so no data or relationships are lost
        if (Schema::hasTable('clients')) {
            $existingClients = DB::table('clients')->get();
            foreach ($existingClients as $c) {
                $userEmail = !empty($c->billing_email) ? $c->billing_email : 'client_' . $c->id . '@client.local';
                $existingUser = DB::table('users')->where('email', $userEmail)->first();

                if (!$existingUser) {
                    $userId = DB::table('users')->insertGetId([
                        'organization_id' => $c->organization_id,
                        'name' => $c->name,
                        'email' => $userEmail,
                        'password' => bcrypt(Str::random(16)),
                        'status' => $c->status ?? 'active',
                        'created_at' => $c->created_at ?? now(),
                        'updated_at' => $c->updated_at ?? now(),
                    ]);
                } else {
                    $userId = $existingUser->id;
                }

                // Re-point dependent records from old client ID to new user ID
                foreach (['projects', 'leads', 'client_wallets', 'credit_transactions', 'invoices', 'lead_communications', 'recharge_requests'] as $tbl) {
                    if (Schema::hasTable($tbl)) {
                        DB::table($tbl)->where('client_id', $c->id)->update(['client_id' => $userId]);
                    }
                }
            }
        }

        // 2. Drop foreign key constraints pointing to clients table
        if (Schema::hasTable('client_users')) {
            try {
                Schema::table('client_users', function (Blueprint $table) {
                    $table->dropForeign(['client_id']);
                });
            } catch (\Throwable $e) {}

            try {
                Schema::table('client_users', function (Blueprint $table) {
                    $table->dropForeign(['user_id']);
                });
            } catch (\Throwable $e) {}

            Schema::dropIfExists('client_users');
        }

        $tablesWithClientFk = [
            'client_wallets',
            'credit_transactions',
            'invoices',
            'leads',
            'lead_communications',
            'projects',
            'recharge_requests',
        ];

        foreach ($tablesWithClientFk as $tbl) {
            if (Schema::hasTable($tbl)) {
                try {
                    Schema::table($tbl, function (Blueprint $table) {
                        $table->dropForeign(['client_id']);
                    });
                } catch (\Throwable $e) {}
            }
        }

        // 3. Drop the clients table
        Schema::dropIfExists('clients');

        // 4. Re-bind client_id on all related tables to the users table
        $validUserIds = DB::table('users')->pluck('id')->toArray();

        // Projects
        if (Schema::hasTable('projects')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->unsignedBigInteger('client_id')->nullable()->change();
            });
            if (!empty($validUserIds)) {
                DB::table('projects')->whereNotIn('client_id', $validUserIds)->update(['client_id' => null]);
            }
            Schema::table('projects', function (Blueprint $table) {
                $table->foreign('client_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        // Leads
        if (Schema::hasTable('leads')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->unsignedBigInteger('client_id')->nullable()->change();
            });
            if (!empty($validUserIds)) {
                DB::table('leads')->whereNotIn('client_id', $validUserIds)->update(['client_id' => null]);
            }
            Schema::table('leads', function (Blueprint $table) {
                $table->foreign('client_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        // Client Wallets
        if (Schema::hasTable('client_wallets')) {
            if (!empty($validUserIds)) {
                DB::table('client_wallets')->whereNotIn('client_id', $validUserIds)->delete();
            }
            Schema::table('client_wallets', function (Blueprint $table) {
                $table->foreign('client_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        // Credit Transactions
        if (Schema::hasTable('credit_transactions')) {
            Schema::table('credit_transactions', function (Blueprint $table) {
                $table->unsignedBigInteger('client_id')->nullable()->change();
            });
            if (!empty($validUserIds)) {
                DB::table('credit_transactions')->whereNotIn('client_id', $validUserIds)->update(['client_id' => null]);
            }
            Schema::table('credit_transactions', function (Blueprint $table) {
                $table->foreign('client_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        // Invoices
        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->unsignedBigInteger('client_id')->nullable()->change();
            });
            if (!empty($validUserIds)) {
                DB::table('invoices')->whereNotIn('client_id', $validUserIds)->update(['client_id' => null]);
            }
            Schema::table('invoices', function (Blueprint $table) {
                $table->foreign('client_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        // Lead Communications
        if (Schema::hasTable('lead_communications')) {
            Schema::table('lead_communications', function (Blueprint $table) {
                $table->unsignedBigInteger('client_id')->nullable()->change();
            });
            if (!empty($validUserIds)) {
                DB::table('lead_communications')->whereNotIn('client_id', $validUserIds)->update(['client_id' => null]);
            }
            Schema::table('lead_communications', function (Blueprint $table) {
                $table->foreign('client_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        // Recharge Requests
        if (Schema::hasTable('recharge_requests')) {
            Schema::table('recharge_requests', function (Blueprint $table) {
                $table->unsignedBigInteger('client_id')->nullable()->change();
            });
            if (!empty($validUserIds)) {
                DB::table('recharge_requests')->whereNotIn('client_id', $validUserIds)->update(['client_id' => null]);
            }
            Schema::table('recharge_requests', function (Blueprint $table) {
                $table->foreign('client_id')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Drop foreign keys referencing users
        $tablesWithClientFk = [
            'client_wallets',
            'credit_transactions',
            'invoices',
            'leads',
            'lead_communications',
            'projects',
            'recharge_requests',
        ];

        foreach ($tablesWithClientFk as $tbl) {
            if (Schema::hasTable($tbl)) {
                try {
                    Schema::table($tbl, function (Blueprint $table) {
                        $table->dropForeign(['client_id']);
                    });
                } catch (\Throwable $e) {}
            }
        }

        // 2. Re-create clients table
        if (!Schema::hasTable('clients')) {
            Schema::create('clients', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
                $table->string('name');
                $table->string('billing_email')->nullable();
                $table->string('status')->default('active')->index();
                $table->timestamps();
                $table->index('created_at');
            });
        }

        // 3. Re-create client_users pivot
        if (!Schema::hasTable('client_users')) {
            Schema::create('client_users', function (Blueprint $table) {
                $table->id();
                $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->boolean('is_primary_contact')->default(false);
                $table->timestamps();
                $table->unique(['client_id', 'user_id']);
            });
        }

        // 4. Re-bind foreign keys back to clients table
        foreach ($tablesWithClientFk as $tbl) {
            if (Schema::hasTable($tbl)) {
                try {
                    Schema::table($tbl, function (Blueprint $table) {
                        $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
                    });
                } catch (\Throwable $e) {}
            }
        }
    }
};

