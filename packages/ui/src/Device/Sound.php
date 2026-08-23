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
 * Plays a short sound via a fire-and-forget MediaPlayer (see
 * NativeDeviceBridge.kt's playSound()) — an action-string builder, not
 * a widget: attach Sound::playAction() to any Button.
 *
 * $url must be reachable from the device (an absolute http(s):// URL —
 * e.g. one served from this app's own public/assets/, the same way
 * NativeWidgetsPaymentsScreen's own assets are). Fire-and-forget, no
 * result field — there's no meaningful "it worked" signal beyond audio
 * actually playing.
 */
final class Sound
{
    public static function playAction(string $url): string
    {
        return 'device:sound:' . rawurlencode($url);
    }
}
