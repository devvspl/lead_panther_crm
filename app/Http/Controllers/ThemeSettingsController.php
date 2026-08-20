<?php

namespace App\Http\Controllers;

use App\Models\GeneralSetting;
use App\Support\ThemeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ThemeSettingsController extends Controller
{
    /**
     * Keys allowed for user theme customization.
     */
    protected array $themeKeys = [
        'theme_primary_color',
        'theme_secondary_color',
        'theme_accent_color',
        'theme_sidebar_bg',
        'theme_sidebar_text',
        'theme_active_menu_style',
        'theme_active_menu_color',
        'theme_active_menu_bg',
        'theme_header_bg',
        'theme_header_text',
        'theme_page_bg',
        'theme_card_bg',
        'theme_border_color',
        'theme_font_family',
        'theme_font_size',
        'theme_border_radius',
        'theme_mode',
    ];

    /**
     * Update the authenticated user's theme settings via AJAX.
     */
    public function update(Request $request): JsonResponse
    {
        $userId = auth()->id();

        foreach ($this->themeKeys as $key) {
            if ($request->has($key)) {
                $val = $request->input($key);
                GeneralSetting::setValue($userId, $key, $val);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Theme customizations saved successfully!',
            'theme' => ThemeService::getUserTheme($userId),
        ]);
    }

    /**
     * Reset user theme customization to system defaults.
     */
    public function reset(): JsonResponse
    {
        $userId = auth()->id();

        GeneralSetting::where('user_id', $userId)
            ->where('key', 'like', 'theme_%')
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Theme reset to defaults successfully!',
            'theme' => ThemeService::getUserTheme($userId),
        ]);
    }
}
