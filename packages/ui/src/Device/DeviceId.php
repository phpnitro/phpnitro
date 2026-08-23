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
 * Reads Settings.Secure.ANDROID_ID (see NativeDeviceBridge.kt's
 * deviceId()) — an action-string builder, not a widget: attach
 * DeviceId::readAction() to any Button.
 *
 * Resets on factory reset, and (since Android 8) is scoped per app-signing
 * key per user — not a stable cross-app device fingerprint, just a stable
 * per-app-install identifier. Result lands in $_GET[$outputField].
 */
final class DeviceId
{
    public static function readAction(string $outputField = 'device_id_out'): string
    {
        return "device:deviceid:{$outputField}";
    }

    public static function result(string $outputField = 'device_id_out'): ?string
    {
        return $_GET[$outputField] ?? null;
    }
}
