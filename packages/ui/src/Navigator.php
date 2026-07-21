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
 * There is no in-memory route stack here — every "navigation" is a real
 * URL and a real HTTP redirect (see Screen::handle(), which turns an
 * onXxx() return value into a Location header). Navigator is naming
 * sugar over that reality, mirroring Flutter's push/pop vocabulary
 * without pretending to be a route stack.
 */
final class Navigator
{
    /** Return from an onXxx() handler to redirect to $path (Navigator.push equivalent). */
    public static function to(string $path): string
    {
        return $path;
    }

    /** Return from an onXxx() handler to redirect back to the previous page (Navigator.pop equivalent). */
    public static function back(string $fallback = '/'): string
    {
        return $_SERVER['HTTP_REFERER'] ?? $fallback;
    }

    public static function link(string $label, string $path, string $classes = 'text-blue-600 hover:underline'): Link
    {
        return Link::make($label, $path, $classes);
    }
}
