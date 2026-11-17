<?php
namespace App\Support;
use App\Models\User;
class LeadSourceVisibility {
    public static function allowed(?User $user = null): bool {
        $user ??= auth()->user();
        return ! in_array(NavigationConfig::getRoleKey($user), ['builder', 'team-leader', 'sales-executive'], true);
    }
}
