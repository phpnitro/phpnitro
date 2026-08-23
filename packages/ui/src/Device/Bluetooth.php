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
 * Reads the Bluetooth adapter's on/off state (see NativeDeviceBridge.kt's
 * bluetoothState()) — an action-string builder, not a widget: attach
 * Bluetooth::stateAction() to any Button.
 *
 * Never triggers pairing/scanning UI, just a state read. Result lands in
 * $_GET[$outputField] as 'unsupported' (no Bluetooth radio), 'off', or
 * 'on'.
 */
final class Bluetooth
{
    public static function stateAction(string $outputField = 'bt_out'): string
    {
        return "device:bluetooth:{$outputField}";
    }

    public static function result(string $outputField = 'bt_out'): ?string
    {
        return $_GET[$outputField] ?? null;
    }
}
