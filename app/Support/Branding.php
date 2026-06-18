<?php

namespace App\Support;

/**
 * White-label branding (panel name, accent colour, logo) set by the operator and
 * stored in settings. Shared to every page + injected into the root view so a
 * reseller's panel carries their identity with no flash and no redeploy.
 */
class Branding
{
    public const DEFAULT_NAME = 'ConvoroCP';

    public const DEFAULT_ACCENT = '#5B5BD6';

    public static function data(): array
    {
        return [
            'name' => Setting::get('brand.name') ?: self::DEFAULT_NAME,
            'accent' => self::accent(),
            'logo' => Setting::get('brand.logo') ?: null,
        ];
    }

    public static function accent(): string
    {
        $a = Setting::get('brand.accent');

        return is_string($a) && preg_match('/^#[0-9a-fA-F]{6}$/', $a) ? $a : self::DEFAULT_ACCENT;
    }
}
