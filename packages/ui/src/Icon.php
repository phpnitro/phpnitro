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

    public static function warning(string $classes = 'w-5 h-5'): string
    {
        return self::wrap(
            $classes,
            '<path d="M12 3.5 21.5 20h-19L12 3.5Z" fill="none" stroke="currentColor" '
            . 'stroke-width="1.5" stroke-linejoin="round"/>'
            . '<line x1="12" y1="9.5" x2="12" y2="14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>'
            . '<circle cx="12" cy="17" r="1" fill="currentColor"/>',
        );
    }

    public static function check(string $classes = 'w-5 h-5'): string
    {
        return self::wrap(
            $classes,
            '<polyline points="4,13 9,18 20,6" fill="none" stroke="currentColor" stroke-width="2" '
            . 'stroke-linecap="round" stroke-linejoin="round"/>',
        );
    }

    public static function close(string $classes = 'w-5 h-5'): string
    {
        return self::wrap(
            $classes,
            '<line x1="5" y1="5" x2="19" y2="19" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>'
            . '<line x1="19" y1="5" x2="5" y2="19" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>',
        );
    }

    public static function search(string $classes = 'w-5 h-5'): string
    {
        return self::wrap(
            $classes,
            '<circle cx="10.5" cy="10.5" r="6.5" fill="none" stroke="currentColor" stroke-width="1.6"/>'
            . '<line x1="15.3" y1="15.3" x2="21" y2="21" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>',
        );
    }

    public static function heart(string $classes = 'w-5 h-5'): string
    {
        return self::wrap(
            $classes,
            '<path d="M12 20.5 4.6 13C2.5 10.9 2.5 7.6 4.6 5.6c2-2 5.2-2 7.1.1L12 6l.3-.3c1.9-2.1 5.1-2.1 7.1-.1 '
            . '2.1 2 2.1 5.3 0 7.4L12 20.5Z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>',
        );
    }

    public static function star(string $classes = 'w-5 h-5'): string
    {
        return self::wrap(
            $classes,
            '<polygon points="12,2.5 14.9,8.6 21.5,9.4 16.8,14 18,20.5 12,17.3 6,20.5 7.2,14 2.5,9.4 9.1,8.6" '
            . 'fill="none" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/>',
        );
    }

    public static function trash(string $classes = 'w-5 h-5'): string
    {
        return self::wrap(
            $classes,
            '<line x1="4" y1="7" x2="20" y2="7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>'
            . '<path d="M6 7h12l-1 13a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2Z" fill="none" stroke="currentColor" '
            . 'stroke-width="1.6" stroke-linejoin="round"/>'
            . '<line x1="9.5" y1="4" x2="14.5" y2="4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>',
        );
    }

    public static function edit(string $classes = 'w-5 h-5'): string
    {
        return self::wrap(
            $classes,
            '<path d="M4 20h16" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>'
            . '<path d="M15.5 4.5 19.5 8.5 8 20H4v-4Z" fill="none" stroke="currentColor" '
            . 'stroke-width="1.5" stroke-linejoin="round"/>',
        );
    }

    public static function download(string $classes = 'w-5 h-5'): string
    {
        return self::wrap(
            $classes,
            '<line x1="12" y1="3" x2="12" y2="15" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>'
            . '<polyline points="7,10 12,15 17,10" fill="none" stroke="currentColor" stroke-width="1.6" '
            . 'stroke-linecap="round" stroke-linejoin="round"/>'
            . '<line x1="4" y1="20" x2="20" y2="20" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>',
        );
    }

    public static function upload(string $classes = 'w-5 h-5'): string
    {
        return self::wrap(
            $classes,
            '<line x1="12" y1="15" x2="12" y2="3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>'
            . '<polyline points="7,8 12,3 17,8" fill="none" stroke="currentColor" stroke-width="1.6" '
            . 'stroke-linecap="round" stroke-linejoin="round"/>'
            . '<line x1="4" y1="20" x2="20" y2="20" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>',
        );
    }

    public static function share(string $classes = 'w-5 h-5'): string
    {
        return self::wrap(
            $classes,
            '<circle cx="18" cy="6" r="2.4" fill="none" stroke="currentColor" stroke-width="1.5"/>'
            . '<circle cx="18" cy="18" r="2.4" fill="none" stroke="currentColor" stroke-width="1.5"/>'
            . '<circle cx="6" cy="12" r="2.4" fill="none" stroke="currentColor" stroke-width="1.5"/>'
            . '<line x1="8.1" y1="10.8" x2="15.9" y2="7.2" stroke="currentColor" stroke-width="1.4"/>'
            . '<line x1="8.1" y1="13.2" x2="15.9" y2="16.8" stroke="currentColor" stroke-width="1.4"/>',
        );
    }

    public static function calendar(string $classes = 'w-5 h-5'): string
    {
        return self::wrap(
            $classes,
            '<rect x="3.5" y="5.5" width="17" height="15" rx="2" fill="none" stroke="currentColor" stroke-width="1.5"/>'
            . '<line x1="3.5" y1="10" x2="20.5" y2="10" stroke="currentColor" stroke-width="1.5"/>'
            . '<line x1="8" y1="3.5" x2="8" y2="7.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>'
            . '<line x1="16" y1="3.5" x2="16" y2="7.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>',
        );
    }

    public static function clock(string $classes = 'w-5 h-5'): string
    {
        return self::wrap(
            $classes,
            '<circle cx="12" cy="12" r="8.5" fill="none" stroke="currentColor" stroke-width="1.5"/>'
            . '<polyline points="12,7 12,12 16,14.5" fill="none" stroke="currentColor" stroke-width="1.5" '
            . 'stroke-linecap="round" stroke-linejoin="round"/>',
        );
    }

    public static function mail(string $classes = 'w-5 h-5'): string
    {
        return self::wrap(
            $classes,
            '<rect x="3" y="5.5" width="18" height="13" rx="2" fill="none" stroke="currentColor" stroke-width="1.5"/>'
            . '<polyline points="3.5,6.5 12,13 20.5,6.5" fill="none" stroke="currentColor" stroke-width="1.5" '
            . 'stroke-linecap="round" stroke-linejoin="round"/>',
        );
    }

    public static function phone(string $classes = 'w-5 h-5'): string
    {
        return self::wrap(
            $classes,
            '<path d="M5 4h3.2l1.4 4.4-2 1.8a12 12 0 0 0 6.2 6.2l1.8-2 4.4 1.4V19a2 2 0 0 1-2.2 2 '
            . '17 17 0 0 1-14-14A2 2 0 0 1 5 4Z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>',
        );
    }

    public static function lock(string $classes = 'w-5 h-5'): string
    {
        return self::wrap(
            $classes,
            '<rect x="5" y="11" width="14" height="10" rx="2" fill="none" stroke="currentColor" stroke-width="1.5"/>'
            . '<path d="M8 11V7.5a4 4 0 0 1 8 0V11" fill="none" stroke="currentColor" stroke-width="1.5"/>'
            . '<circle cx="12" cy="16" r="1.4" fill="currentColor"/>',
        );
    }

    public static function bell(string $classes = 'w-5 h-5'): string
    {
        return self::wrap(
            $classes,
            '<path d="M6 17V11a6 6 0 0 1 12 0v6l1.5 2.5h-15Z" fill="none" stroke="currentColor" '
            . 'stroke-width="1.5" stroke-linejoin="round"/>'
            . '<path d="M9.5 20.5a2.5 2.5 0 0 0 5 0" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>',
        );
    }

    public static function plus(string $classes = 'w-5 h-5'): string
    {
        return self::wrap(
            $classes,
            '<line x1="12" y1="4" x2="12" y2="20" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>'
            . '<line x1="4" y1="12" x2="20" y2="12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>',
        );
    }

    public static function minus(string $classes = 'w-5 h-5'): string
    {
        return self::wrap(
            $classes,
            '<line x1="4" y1="12" x2="20" y2="12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>',
        );
    }

    public static function chevronLeft(string $classes = 'w-4 h-4'): string
    {
        return self::wrap(
            $classes,
            '<polyline points="15,6 9,12 15,18" fill="none" stroke="currentColor" stroke-width="1.8" '
            . 'stroke-linecap="round" stroke-linejoin="round"/>',
        );
    }

    public static function chevronRight(string $classes = 'w-4 h-4'): string
    {
        return self::wrap(
            $classes,
            '<polyline points="9,6 15,12 9,18" fill="none" stroke="currentColor" stroke-width="1.8" '
            . 'stroke-linecap="round" stroke-linejoin="round"/>',
        );
    }

    public static function chevronUp(string $classes = 'w-4 h-4'): string
    {
        return self::wrap(
            $classes,
            '<polyline points="6,15 12,9 18,15" fill="none" stroke="currentColor" stroke-width="1.8" '
            . 'stroke-linecap="round" stroke-linejoin="round"/>',
        );
    }

    public static function arrowLeft(string $classes = 'w-5 h-5'): string
    {
        return self::wrap(
            $classes,
            '<line x1="20" y1="12" x2="4" y2="12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>'
            . '<polyline points="10,6 4,12 10,18" fill="none" stroke="currentColor" stroke-width="1.8" '
            . 'stroke-linecap="round" stroke-linejoin="round"/>',
        );
    }

    public static function arrowRight(string $classes = 'w-5 h-5'): string
    {
        return self::wrap(
            $classes,
            '<line x1="4" y1="12" x2="20" y2="12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>'
            . '<polyline points="14,6 20,12 14,18" fill="none" stroke="currentColor" stroke-width="1.8" '
            . 'stroke-linecap="round" stroke-linejoin="round"/>',
        );
    }

    public static function info(string $classes = 'w-5 h-5'): string
    {
        return self::wrap(
            $classes,
            '<circle cx="12" cy="12" r="8.5" fill="none" stroke="currentColor" stroke-width="1.5"/>'
            . '<line x1="12" y1="11" x2="12" y2="16.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>'
            . '<circle cx="12" cy="7.5" r="1" fill="currentColor"/>',
        );
    }

    public static function eye(string $classes = 'w-5 h-5'): string
    {
        return self::wrap(
            $classes,
            '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z" fill="none" '
            . 'stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>'
            . '<circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="1.5"/>',
        );
    }

    private static function wrap(string $classes, string $inner): string
    {
        $classes = htmlspecialchars($classes, ENT_QUOTES);

        return "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 24 24\" class=\"{$classes}\" aria-hidden=\"true\">{$inner}</svg>";
    }
}
