<?php

namespace App\Services;

use App\Models\GeneralSetting;

class EmailDesignService
{
    public const DESIGNS = ['classic' => 'Classic Card', 'executive' => 'Executive Brief', 'minimal' => 'Minimal Letter'];

    public function enabled(): bool
    {
        return GeneralSetting::where('key', 'system_email_notifications_enabled')->value('value') !== '0';
    }

    public function active(): string
    {
        $design = GeneralSetting::where('key', 'system_email_active_design')->value('value');
        return array_key_exists($design ?? '', self::DESIGNS) ? $design : 'classic';
    }
}
