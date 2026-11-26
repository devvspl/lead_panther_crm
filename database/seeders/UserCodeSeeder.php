<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserCodeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Fetch all existing users ordered strictly by id ascending
        $users = User::withoutGlobalScopes()->orderBy('id', 'asc')->get();

        if ($users->isEmpty()) {
            $this->command?->info("No users found to assign UserCodes.");
            return;
        }

        // 2. Assign consecutive UserCodes starting from LP00001 (handling gaps from deleted user IDs)
        $assignedCount = 0;
        $sequence = 1;

        foreach ($users as $user) {
            $expectedCode = sprintf('LP%05d', $sequence);
            $sequence++;

            if ($user->user_code !== $expectedCode) {
                // If the expected code is currently held by a different user record, free it first to avoid unique key collision
                $conflictingUser = User::withoutGlobalScopes()
                    ->where('user_code', $expectedCode)
                    ->where('id', '!=', $user->id)
                    ->first();

                if ($conflictingUser) {
                    $conflictingUser->allowUserCodeChange = true;
                    $conflictingUser->user_code = null;
                    $conflictingUser->saveQuietly();
                }

                $user->allowUserCodeChange = true;
                $user->user_code = $expectedCode;
                $user->saveQuietly();
                $assignedCount++;
            }
        }

        if ($assignedCount === 0) {
            $this->command?->info("All users already have consecutive UserCodes starting from LP00001. No updates required.");
        } else {
            $this->command?->info("Successfully assigned consecutive UserCodes (LP00001 to " . sprintf('LP%05d', $sequence - 1) . ") to {$assignedCount} users.");
        }
    }
}
