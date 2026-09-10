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
 * Reads the Wi-Fi radio's on/off state, and the connected network's own
 * SSID when the location permission needed to read it is already granted
 * (see NativeDeviceBridge.kt's wifiState()) — an action-string builder,
 * not a widget: attach Wifi::stateAction() to any Button.
 *
 * Never triggers Wi-Fi settings UI, just a state read — same "check,
 * never request" contract every other permission-gated capability here
 * follows. Result lands in $_GET[$outputField] as 'unsupported' (no
 * Wi-Fi radio), 'off', 'on', or 'on: <SSID>' when the SSID is readable.
 */
final class Wifi
{
    public static function stateAction(string $outputField = 'wifi_out'): string
    {
        return "device:wifi:{$outputField}";
    }

    public static function result(string $outputField = 'wifi_out'): ?string
    {
        return $_GET[$outputField] ?? null;
    }
}
