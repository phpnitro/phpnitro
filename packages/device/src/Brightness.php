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
 * Sets THIS app window's screen brightness (0.0-1.0) — not the system-wide
 * setting, which would need WRITE_SETTINGS (a special-access permission
 * granted through a system settings screen, not a normal runtime prompt).
 */
final class Brightness
{
    public static function setOnClick(float $level): string
    {
        return sprintf('phpxDevice.setScreenBrightness(%s)', json_encode($level, JSON_THROW_ON_ERROR));
    }
}
