<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine\Device;

/**
 * Opens a URL (http/https/tel/mailto/sms/geo/...) in whatever app the OS
 * resolves it to via Intent.ACTION_VIEW — an action-string builder, not a
 * widget: attach UrlLauncher::openAction() to any Button of your choosing.
 *
 * The URL is rawurlencode()'d into the action string (a raw ":" or "/"
 * would break the "device:" action's colon-separated parsing on the
 * Kotlin side) and decoded back on the way in — a project never needs to
 * think about that encoding, just pass the real URL.
 *
 * Fire-and-forget: there's no result field, the same way tapping a link
 * in a browser doesn't report back whether the target app opened.
 */
final class UrlLauncher
{
    public static function openAction(string $url): string
    {
        return 'device:openurl:' . rawurlencode($url);
    }
}
