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
        $text = FinancialVisibility::text($text);
        $text = static::stripInternalMetaDetails($text);
        return preg_replace(
            ['/manual[ _-]+adjustment/i', '/manual[ _-]+lead[ _-]+entry/i', '/manually/i', '/manual/i'],
            ['Credit Adjustment', 'Lead entry', 'on request', 'On Demand'], $text
        );
    }

    public static function stripInternalMetaDetails(string $text): string
    {
        if ($text === '') {
            return '';
        }

        // 1. Strip Inbox Url line / parameter
        $text = preg_replace('/(?:•\s*)?inbox\s*url\s*:\s*https?:\/\/[^\s•\n]+/iu', '', $text);
        $text = preg_replace('/https?:\/\/business\.facebook\.com[^\s•\n]*/iu', '', $text);

        // 2. Strip Meta Context line (e.g. Meta Context: Campaign: ... | AdSet: ... | Ad: ... | Form: ...)
        $text = preg_replace('/(?:•\s*)?meta\s*context\s*:[^\n•]+/iu', '', $text);

        // 3. Clean up leading Meta Lead Submission: header if nothing or only bullets follow
        $cleaned = trim($text);
        if (preg_match('/^meta\s*lead\s*submission:\s*(?:•\s*)*$/iu', $cleaned)) {
            return '';
        }

        // 4. Clean up consecutive or dangling bullets
        $text = preg_replace('/(?:•\s*){2,}/u', '• ', $text);
        $text = preg_replace('/^[ \t\n\r•\-]+/u', '', $text);
        $text = preg_replace('/[ \t\n\r•\-]+$/u', '', $text);

        return trim($text);
    }

    public static function activity($value): string
    {
        if (LeadSourceVisibility::allowed()) return (string) ($value ?? '');
        $data = json_decode((string) $value, true);
        if (is_array($data)) {
            return self::label(json_encode(FinancialVisibility::redact(LeadSourceVisibility::redact($data)), JSON_UNESCAPED_UNICODE));
        }
        return self::label($value);
    }
}
