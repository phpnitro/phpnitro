<?php

namespace Engine;

/**
 * Minimal inline-SVG icon set — no external font/CDN request, no reproduced
 * third-party path data (avoids silently-wrong bezier curves misremembered
 * from a icon library). Each icon is a small, precise geometric
 * construction we fully control and can verify by rendering it.
 */
final class Icon
{
    public static function home(string $classes = 'w-5 h-5'): string
    {
        return self::wrap(
            $classes,
            '<polygon points="12,3 21,10 21,21 3,21 3,10" fill="none" stroke="currentColor" '
            . 'stroke-width="1.5" stroke-linejoin="round"/>'
            . '<rect x="9.5" y="14" width="5" height="7" fill="none" stroke="currentColor" stroke-width="1.5"/>',
        );
    }

    public static function settings(string $classes = 'w-5 h-5'): string
    {
        $spokes = '';
        foreach ([0, 45, 90, 135, 180, 225, 270, 315] as $angle) {
            $x1 = 12 + 9 * cos(deg2rad($angle));
            $y1 = 12 + 9 * sin(deg2rad($angle));
            $x2 = 12 + 6 * cos(deg2rad($angle));
            $y2 = 12 + 6 * sin(deg2rad($angle));
            $spokes .= sprintf(
                '<line x1="%.2f" y1="%.2f" x2="%.2f" y2="%.2f" stroke="currentColor" stroke-width="1.5"/>',
                $x1,
                $y1,
                $x2,
                $y2,
            );
        }

        return self::wrap(
            $classes,
            $spokes . '<circle cx="12" cy="12" r="3.5" fill="none" stroke="currentColor" stroke-width="1.5"/>',
        );
    }

    public static function camera(string $classes = 'w-5 h-5'): string
    {
        return self::wrap(
            $classes,
            '<rect x="3" y="7" width="18" height="13" rx="2" fill="none" stroke="currentColor" stroke-width="1.5"/>'
            . '<circle cx="12" cy="13.5" r="3.5" fill="none" stroke="currentColor" stroke-width="1.5"/>'
            . '<rect x="8" y="4" width="8" height="3" rx="1" fill="none" stroke="currentColor" stroke-width="1.5"/>',
        );
    }

    public static function bolt(string $classes = 'w-5 h-5'): string
    {
        return self::wrap($classes, '<polygon points="13,2 4,14 11,14 10,22 20,9 13,9" fill="currentColor"/>');
    }

    public static function rocket(string $classes = 'w-5 h-5'): string
    {
        return self::wrap(
            $classes,
            '<path d="M12 2c2.5 2 4 5.5 4 9.5 0 2-.5 3.8-1.2 5.3l-2.8 3.2-2.8-3.2C8.5 15.3 8 13.5 8 11.5 8 7.5 9.5 4 12 2Z" '
            . 'fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>'
            . '<circle cx="12" cy="10" r="1.6" fill="currentColor"/>'
            . '<polygon points="8,14 5,18 8,17" fill="currentColor"/>'
            . '<polygon points="16,14 19,18 16,17" fill="currentColor"/>'
            . '<polygon points="10.5,18.5 12,22 13.5,18.5" fill="currentColor"/>',
        );
    }

    public static function link(string $classes = 'w-5 h-5'): string
    {
        return self::wrap(
            $classes,
            '<rect x="3" y="9" width="8" height="6" rx="3" fill="none" stroke="currentColor" stroke-width="1.5"/>'
            . '<rect x="13" y="9" width="8" height="6" rx="3" fill="none" stroke="currentColor" stroke-width="1.5"/>'
            . '<line x1="9" y1="12" x2="15" y2="12" stroke="currentColor" stroke-width="1.5"/>',
        );
    }

    public static function hamburger(string $classes = 'w-6 h-6'): string
    {
        return self::wrap(
            $classes,
            '<line x1="4" y1="7" x2="20" y2="7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>'
            . '<line x1="4" y1="12" x2="20" y2="12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>'
            . '<line x1="4" y1="17" x2="20" y2="17" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>',
        );
    }

    public static function chevronDown(string $classes = 'w-4 h-4'): string
    {
        return self::wrap(
            $classes,
            '<polyline points="6,9 12,15 18,9" fill="none" stroke="currentColor" stroke-width="1.8" '
            . 'stroke-linecap="round" stroke-linejoin="round"/>',
        );
    }

    public static function cart(string $classes = 'w-5 h-5'): string
    {
        return self::wrap(
            $classes,
            '<path d="M3 4h2l2.4 12.4a2 2 0 0 0 2 1.6h7.2a2 2 0 0 0 2-1.6L20 8H6" fill="none" '
            . 'stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>'
            . '<circle cx="10" cy="21" r="1.4" fill="currentColor"/>'
            . '<circle cx="17" cy="21" r="1.4" fill="currentColor"/>',
        );
    }

    public static function user(string $classes = 'w-5 h-5'): string
    {
        return self::wrap(
            $classes,
            '<circle cx="12" cy="8" r="3.5" fill="none" stroke="currentColor" stroke-width="1.5"/>'
            . '<path d="M4.5 20c1.2-3.6 4.2-5.5 7.5-5.5s6.3 1.9 7.5 5.5" fill="none" '
            . 'stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>',
        );
    }

    private static function wrap(string $classes, string $inner): string
    {
        $classes = htmlspecialchars($classes, ENT_QUOTES);

        return "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 24 24\" class=\"{$classes}\" aria-hidden=\"true\">{$inner}</svg>";
    }
}
