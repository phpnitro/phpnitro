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
 * Reads the current battery level via BatteryManager (see
 * NativeDeviceBridge.kt's batteryLevel()) — an action-string builder, not
 * a widget: attach Battery::readAction() to any Button.
 *
 * Result lands in $_GET[$outputField] as "N%" (e.g. "82%").
 */
final class Battery
{
    public static function readAction(string $outputField = 'battery_out'): string
    {
        return "device:battery:{$outputField}";
    }

    public static function result(string $outputField = 'battery_out'): ?string
    {
        return $_GET[$outputField] ?? null;
    }
}
