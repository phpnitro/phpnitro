<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine\Native;

use Engine\Color;

/**
 * A small fixed design-token set (spacing/radius/type scale/color roles)
 * instead of hand-picked numbers scattered across every screen — the gap
 * between "a layout engine that works" and "an app that looks designed"
 * is mostly this: every card, button and label pulling from the same
 * scale instead of an ad-hoc "18px here, 20px there".
 *
 * Modeled on captures/ (a minimalist Flutter reference: near-black ink on
 * white, thin borders instead of heavy shadows, pill-radius CTA buttons)
 * rather than the earlier colored-card/Material-elevation pass — flat and
 * high-contrast, not shadow-driven.
 */
final class Tokens
{
    // Spacing scale (px) — every gap/padding in a screen should be one of
    // these, not an arbitrary number.
    public const SPACE_XS = 4.0;
    public const SPACE_SM = 8.0;
    public const SPACE_MD = 12.0;
    public const SPACE_LG = 16.0;
    public const SPACE_XL = 20.0;
    public const SPACE_XXL = 28.0;

    // Corner radius scale.
    public const RADIUS_SM = 10.0;
    public const RADIUS_MD = 14.0;
    public const RADIUS_LG = 18.0;
    public const RADIUS_PILL = 999.0;

    // Type scale (px).
    public const TEXT_DISPLAY = 26.0;
    public const TEXT_TITLE = 19.0;
    public const TEXT_BODY = 15.0;
    public const TEXT_BODY_SMALL = 13.0;
    public const TEXT_CAPTION = 12.0;

    // Same "one static, overwritten unconditionally at the top of every
    // request before any layout()/paint() call runs" pattern as
    // MediaQuery — see that class's own docblock for why this is safe
    // despite PHP's built-in dev server reusing one process across
    // requests. init() is called from public/index.php right after
    // MediaQuery::init(), reading a "?dark=1" query param
    // NativeRenderPocActivity sends based on the device's real system
    // dark-mode setting (Configuration.uiMode) — the same "system, not a
    // manually-chosen app setting" default Flutter/RN apps start from.
    private static bool $isDark = false;

    public static function init(bool $isDark): void
    {
        self::$isDark = $isDark;
    }

    public static function isDark(): bool
    {
        return self::$isDark;
    }

    public static function ink(): Color
    {
        return self::$isDark ? Color::gray(50) : Color::gray(900);
    }

    public static function inkSecondary(): Color
    {
        return self::$isDark ? Color::gray(400) : Color::gray(500);
    }

    public static function inkMuted(): Color
    {
        return self::$isDark ? Color::gray(500) : Color::gray(400);
    }

    public static function surface(): Color
    {
        return self::$isDark ? Color::gray(900) : Color::white();
    }

    public static function surfaceMuted(): Color
    {
        return self::$isDark ? Color::gray(800) : Color::gray(50);
    }

    public static function border(): Color
    {
        return self::$isDark ? Color::gray(700) : Color::gray(200);
    }

    public static function success(): Color
    {
        // A step lighter in dark mode (400 vs 600) — the same shade that
        // reads as a confident, saturated green on white reads as muddy
        // and low-contrast on a near-black surface; every accent color
        // below follows the same one-step-lighter-in-dark adjustment.
        return Color::green(self::$isDark ? 400 : 600);
    }

    public static function successMuted(): Color
    {
        return self::$isDark ? Color::green(900) : Color::green(50);
    }

    public static function danger(): Color
    {
        return Color::red(self::$isDark ? 400 : 600);
    }

    public static function dangerMuted(): Color
    {
        return self::$isDark ? Color::red(900) : Color::red(50);
    }
}
