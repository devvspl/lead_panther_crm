<?php
namespace App\Support;

use App\Models\User;

class ClientFacingText
{
    /** Presentation only: keep persisted enum values and customer records unchanged. */
    public static function label($value, ?User $user = null): string
    {
        $text = (string) ($value ?? '');
        $user ??= auth()->user();
        if (!$user || NavigationConfig::getRoleKey($user) === 'super-admin') return $text;
        return preg_replace(
            ['/manual[ _-]+adjustment/i', '/manual[ _-]+lead[ _-]+entry/i', '/manually/i', '/manual/i'],
            ['Balance Adjustment', 'Lead entry', 'on request', 'On Demand'], $text
        );
    }

    public static function activity($value): string
    {
        if (LeadSourceVisibility::allowed()) return (string) ($value ?? '');
        $data = json_decode((string) $value, true);
        if (is_array($data)) {
            return self::label(json_encode(LeadSourceVisibility::redact($data), JSON_UNESCAPED_UNICODE));
        }
        return self::label($value);
    }
}
