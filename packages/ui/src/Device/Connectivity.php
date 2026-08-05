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
 * Reports whether the device currently has internet connectivity — a real
 * android.net.ConnectivityManager check (NativeDeviceBridge.kt's own
 * isOnline(), already shared with the WebView path's
 * Engine\Connectivity\ConnectivityBadge), not a guess from whether this
 * very request reached the server.
 *
 * An action-string builder, not a widget: attach Connectivity::checkAction()
 * to any Button of your choosing.
 *
 * Result lands in $_GET[$outputField] as 'online' or 'offline'.
 */
final class Connectivity
{
    public static function checkAction(string $outputField = 'connectivity_out'): string
    {
        return "device:connectivity:{$outputField}";
    }

    public static function result(string $outputField = 'connectivity_out'): ?string
    {
        return $_GET[$outputField] ?? null;
    }
}
