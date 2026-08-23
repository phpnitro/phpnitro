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
 * Sets THIS activity window's screen brightness override (see
 * NativeDeviceBridge.kt's setBrightness() — WindowManager.LayoutParams,
 * not the system-wide brightness setting) — an action-string builder,
 * not a widget: attach Brightness::setAction() to any Button.
 *
 * $level is clamped to [0.01, 1.0] on the Kotlin side (0 would turn the
 * screen fully off, not just dim it). Fire-and-forget, no result field.
 */
final class Brightness
{
    public static function setAction(float $level): string
    {
        return "device:brightness:{$level}";
    }
}
