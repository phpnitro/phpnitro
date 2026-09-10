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
 * Reads the device's airplane-mode on/off state (see NativeDeviceBridge.kt's
 * airplaneModeState()) — an action-string builder, not a widget: attach
 * AirplaneMode::stateAction() to any Button.
 *
 * Read-only on both platforms — neither Android nor iOS expose a public
 * API to toggle airplane mode from a third-party app at all (a real OS
 * restriction, not a narrower implementation here). Result lands in
 * $_GET[$outputField] as 'on' or 'off'.
 */
final class AirplaneMode
{
    public static function stateAction(string $outputField = 'airplane_out'): string
    {
        return "device:airplanemode:{$outputField}";
    }

    public static function result(string $outputField = 'airplane_out'): ?string
    {
        return $_GET[$outputField] ?? null;
    }
}
