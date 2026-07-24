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
 * Accelerometer/gyroscope/compass — no Web API equivalent reliable enough
 * across browsers to fall back to (DeviceMotionEvent only approximates the
 * accelerometer, inconsistently), so this is native-only: no bridge means
 * no readings, not a degraded simulation.
 */
final class Sensors
{
    public const ACCELEROMETER = 1;
    public const MAGNETIC_FIELD = 2;
    public const GYROSCOPE = 4;

    public static function startOnClick(int $sensorType, string $outputId): string
    {
        return sprintf("phpxDevice.startSensor(%d, '%s')", $sensorType, $outputId);
    }

    public static function stopOnClick(int $sensorType): string
    {
        return sprintf('phpxDevice.stopSensor(%d)', $sensorType);
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
