<?php

namespace App\Support;

use App\Models\User;

class FinancialVisibility
{
    public static function allowed(?User $user = null): bool
    {
        $user ??= auth()->user();
        return $user?->status === 'active' && NavigationConfig::getRoleKey($user) === 'super-admin';
    }
    public static function sensitive(string $key): bool
    {
        return (bool) preg_match('/(?:^|[._])(?:budget(?:_formatted)?|price(?:_range_(?:min|max))?|booking_(?:amount|value)|payment_amount|quoted_amount|agreed_amount|revenue|total_revenue|outstanding|spend|amount)(?:$|[._])/i', $key);
    }
    public static function text(?string $text): string
    {
        return preg_replace('/^.*(?:budget\s*[:=]|(?:booking|payment|quoted|agreed)\s+(?:amount|value)\s*[:=]|₹|INR\s*\d).*$(?:\R)?/miu', '', $text ?? '');
    }
    public static function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (self::sensitive((string) $key)) unset($data[$key]);
            elseif (is_array($value)) $data[$key] = self::redact($value);
            elseif (is_string($value)) $data[$key] = self::text($value);
        }
        return $data;
    }
}
