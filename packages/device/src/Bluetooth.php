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
 * Adapter state + already-bonded (paired) devices only. A full BLE
 * discovery scan needs a foreground service and more careful
 * location-permission handling than this bridge covers yet — see
 * ROADMAP-PARITE-FLUTTER-REACT-NATIVE.md.
 */
final class Bluetooth
{
    public static function onClick(string $outputId): string
    {
        return sprintf("phpxDevice.showBluetoothInfo('%s')", $outputId);
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
