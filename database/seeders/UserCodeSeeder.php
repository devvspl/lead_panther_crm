<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserCodeSeeder extends Seeder
{
    /**
     * Check if a code matches the valid short UserCode format: LP followed by 5+ digits.
     */
    public static function isValidUserCode(?string $code): bool
    {
        if (empty($code)) {
            return false;
        }

        return (bool) preg_match('/^LP\d{5,}$/i', trim($code));
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Ensure user_code column exists
        if (!Schema::hasColumn('users', 'user_code')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('user_code', 30)->nullable()->unique()->after('id');
            });
        }

        // 2. Fetch users that need a valid UserCode (either null, empty, or non-standard format)
        $allUsers = User::withoutGlobalScopes()->orderBy('id', 'asc')->get();

        $usersNeedingCode = $allUsers->filter(function ($user) {
            return !static::isValidUserCode($user->user_code);
        });

        if ($usersNeedingCode->isEmpty()) {
            $this->command?->info("All users already have a valid UserCode. No updates required.");
            return;
        }

        $assignedCount = 0;

        DB::transaction(function () use ($usersNeedingCode, &$assignedCount) {
            foreach ($usersNeedingCode as $user) {
                // If user had a legacy code like LP-000062 or LP-62, extract the digits
                $candidate = null;
                if (!empty($user->user_code) && preg_match('/^LP-?(\d+)$/i', $user->user_code, $matches)) {
                    $candidate = sprintf('LP%05d', (int) $matches[1]);
                } else {
                    // Try deterministic sequential code matching user id
                    $candidate = sprintf('LP%05d', $user->id);
                }

                // If candidate is already taken by another user, generate the next unique code
                if (User::withoutGlobalScopes()->where('user_code', $candidate)->where('id', '!=', $user->id)->exists()) {
                    $candidate = User::generateUniqueUserCode('LP');
                }

                $user->allowUserCodeChange = true;
                $user->user_code = $candidate;
                $user->saveQuietly();

                $assignedCount++;
            }
        });

        $this->command?->info("Successfully assigned unique UserCodes to {$assignedCount} existing users.");
    }
}
