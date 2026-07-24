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

use Engine\Html;
use Engine\Widget;

/**
 * Settings.Secure.ANDROID_ID — resettable on factory reset, different per
 * app signing key since Android 8, not the IMEI/hardware serial (which
 * would need a special permission Play Store apps can't request at all).
 */
final class DeviceId
{
    public static function onClick(string $outputId): string
    {
        return sprintf("phpxDevice.showDeviceId('%s')", $outputId);
    }

    public static function outputElement(string $id, string $classes = 'text-sm text-gray-500 dark:text-gray-400'): Widget
    {
        return Html::raw(sprintf(
            '<span id="%s" class="%s"></span>',
            htmlspecialchars($id, ENT_QUOTES),
            htmlspecialchars($classes, ENT_QUOTES),
        ));
    }
}
