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

/**
 * Flutter's MediaQuery.of(context).size, minus the context — every screen's
 * build() already receives $screenWidth/$screenHeight as explicit
 * parameters (see any Native*Screen::build() signature), which is fine for
 * a screen's own top-level layout decisions but means a widget several
 * constructors deep (a reusable component that isn't a whole screen) has
 * no way to know the viewport size unless every caller in between
 * remembers to thread it through — exactly the pain MediaQuery.of(context)
 * exists to avoid in Flutter.
 *
 * init() is called once per request, right after public/index.php reads
 * ?width=/?height= from the query string — before that point nothing has
 * built a tree yet, so nothing could have read a stale value. Safe as a
 * static despite PHP's built-in dev server reusing one process across
 * requests (unlike a real per-request-process CGI model) precisely
 * because init() unconditionally overwrites both fields before any
 * layout()/paint() call runs, every single request.
 */
final class MediaQuery
{
    private static float $width = 360.0;
    private static float $height = 720.0;

    public static function init(float $width, float $height): void
    {
        self::$width = $width;
        self::$height = $height;
    }

    public static function width(): float
    {
        return self::$width;
    }

    public static function height(): float
    {
        return self::$height;
    }

    public static function size(): Size
    {
        return new Size(self::$width, self::$height);
    }

    /** Matches Flutter's own convenience check — most navigation/list layouts only care about this. */
    public static function isLandscape(): bool
    {
        return self::$width > self::$height;
    }
}
