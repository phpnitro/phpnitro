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
 * Reads a single accelerometer snapshot (see NativeDeviceBridge.kt's
 * readSensor(), hardcoded to TYPE_ACCELEROMETER) — an action-string
 * builder, not a widget: attach Sensors::readAccelerometerAction() to
 * any Button.
 *
 * A one-shot reading, not a continuous stream — this pipeline's paint
 * model is one render per request, so there's no screen to keep
 * updating between renders anyway. Result lands in $_GET[$outputField]
 * as "x.xx, y.yy, z.zz" (m/s²) or "Capteur indisponible".
 */
final class Sensors
{
    public static function readAccelerometerAction(string $outputField = 'sensor_out'): string
    {
        return "device:sensor:{$outputField}";
    }

    public static function result(string $outputField = 'sensor_out'): ?string
    {
        return $_GET[$outputField] ?? null;
    }
}
