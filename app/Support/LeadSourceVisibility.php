<?php
namespace App\Support;
use App\Models\User;
class LeadSourceVisibility {
    public static function allowed(?User $user = null): bool {
        $user ??= auth()->user();
        return NavigationConfig::getRoleKey($user) === 'super-admin';
    }

    public static function redact(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (preg_match('/^(?:lead_?source(?:_?(?:id|name))?|source(?:_?(?:id|name))?|metadata|meta)$/i', (string) $key)) unset($payload[$key]);
            elseif (is_array($value)) $payload[$key] = self::redact($value);
        }
        return $payload;
    }
}
