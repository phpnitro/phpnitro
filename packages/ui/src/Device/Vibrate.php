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
 * Triggers a one-shot vibration (see NativeDeviceBridge.kt's vibrate())
 * — an action-string builder, not a widget: attach
 * Vibrate::vibrateAction() to any Button.
 *
 * Fire-and-forget, no result field — there's no meaningful "it worked"
 * signal beyond the device actually buzzing.
 */
final class Vibrate
{
    public static function vibrateAction(int $milliseconds = 200): string
    {
        return "device:vibrate:{$milliseconds}";
    }
}
