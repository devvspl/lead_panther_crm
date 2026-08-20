<?php

namespace App\Support;

use App\Models\GeneralSetting;

class ThemeService
{
    public const DEFAULT_LIGHT = [
        'theme_primary_color' => '#111827',
        'theme_secondary_color' => '#4B5563',
        'theme_accent_color' => '#111827',
        'theme_sidebar_bg' => '#FFFFFF',
        'theme_sidebar_text' => '#6B7280',
        'theme_active_menu_style' => 'highlight', // 'text_only' or 'highlight'
        'theme_active_menu_color' => '#0A0A0A',
        'theme_active_menu_bg' => '#F5F5F5',
        'theme_header_bg' => '#FFFFFF',
        'theme_header_text' => '#0A0A0A',
        'theme_page_bg' => '#F5F5F5',
        'theme_card_bg' => '#FFFFFF',
        'theme_border_color' => '#E5E7EB',
        'theme_font_family' => 'Inter, sans-serif',
        'theme_font_size' => '14px',
        'theme_border_radius' => '0.5rem',
        'theme_mode' => 'light',
    ];

    public const DEFAULT_DARK = [
        'theme_primary_color' => '#3B82F6',
        'theme_secondary_color' => '#94A3B8',
        'theme_accent_color' => '#2563EB',
        'theme_sidebar_bg' => '#0F172A',
        'theme_sidebar_text' => '#94A3B8',
        'theme_active_menu_style' => 'highlight',
        'theme_active_menu_color' => '#F8FAFC',
        'theme_active_menu_bg' => '#1E293B',
        'theme_header_bg' => '#0F172A',
        'theme_header_text' => '#F8FAFC',
        'theme_page_bg' => '#020617',
        'theme_card_bg' => '#0F172A',
        'theme_border_color' => '#1E293B',
        'theme_font_family' => 'Inter, sans-serif',
        'theme_font_size' => '14px',
        'theme_border_radius' => '0.5rem',
        'theme_mode' => 'dark',
    ];

    public const DEFAULTS = self::DEFAULT_LIGHT;

    /**
     * Get theme key-value pairs for the given user, falling back to defaults.
     */
    public static function getUserTheme(?int $userId = null): array
    {
        $userId = $userId ?? auth()->id();
        if (!$userId) {
            return self::DEFAULTS;
        }

        $userSettings = GeneralSetting::where('user_id', $userId)
            ->where('key', 'like', 'theme_%')
            ->pluck('value', 'key')
            ->toArray();

        return array_merge(self::DEFAULTS, $userSettings);
    }
}
