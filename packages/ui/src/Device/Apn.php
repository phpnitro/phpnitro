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
 * Reads the device's preferred APN name, best-effort (see
 * NativeDeviceBridge.kt's apnName()) — an action-string builder, not a
 * widget: attach Apn::readAction() to any Button.
 *
 * Almost always 'unsupported' on a real device on both platforms: this
 * has required carrier privileges to read (not just a runtime
 * permission) since Android 10 for anything beyond an app's own APN
 * entries, and iOS exposes no public API for this at all. Result lands
 * in $_GET[$outputField].
 */
final class Apn
{
    public static function readAction(string $outputField = 'apn_out'): string
    {
        return "device:apn:{$outputField}";
    }

    public static function result(string $outputField = 'apn_out'): ?string
    {
        return $_GET[$outputField] ?? null;
    }
}
