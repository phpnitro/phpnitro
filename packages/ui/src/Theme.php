<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine;

/**
 * ThemeData equivalent — a process-wide, settable default palette. Defaults
 * match what every widget already hardcoded (blue-600 primary, gray-600
 * secondary) so leaving it untouched changes nothing; call Theme::setPrimary()
 * etc. once (e.g. at app bootstrap) to recolor every widget that reads it
 * (FloatingActionButton, ProgressBar, CircularProgress, BottomNavigation's
 * active tab, Checkbox, SwitchToggle) without touching each call site.
 *
 * Not a full ThemeData: no typography scale, no spacing scale, no
 * light/dark-specific palette (see ThemeToggle for that binary switch) —
 * just the small set of colors this framework's widgets actually branch on.
 *
 * Same Tailwind JIT caveat as Button's typed $background: classes built
 * from Theme::primary()->name/shade at runtime are invisible to Tailwind's
 * build-time content scanner unless that exact class string also appears
 * literally somewhere in scanned PHP source — picking an unusual color here
 * with nothing else in the app already using it may need a manual
 * `npm run build` after adding a throwaway reference to force it into
 * public/tailwind.css.
 */
final class Theme
{
    private static ?Color $primary = null;
    private static ?Color $secondary = null;

    public static function setPrimary(Color $color): void
    {
        self::$primary = $color;
    }

    public static function setSecondary(Color $color): void
    {
        self::$secondary = $color;
    }

    public static function primary(): Color
    {
        return self::$primary ?? Color::blue(600);
    }

    public static function secondary(): Color
    {
        return self::$secondary ?? Color::gray(600);
    }

    /**
     * Test isolation only — a real app sets its theme once at bootstrap
     * and never needs to unset it.
     */
    public static function reset(): void
    {
        self::$primary = null;
        self::$secondary = null;
    }
}
