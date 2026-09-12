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
 * Reads the device's last known GPS fix via FusedLocationProviderClient
 * on Android / CLLocationManager on iOS (see NativeDeviceBridge.kt's and
 * NativeDeviceBridge.swift's own getLocation()) — an action-string
 * builder, not a widget: attach Location::fetchAction() to any Button.
 *
 * ACCESS_FINE_LOCATION is only ever checked, never requested — pair
 * with Permission::requestAction('location') first if it might not be
 * granted yet. Result lands in $_GET[$outputField] as "lat, lng" (5
 * decimal places, e.g. "6.36536, 2.41830"), or one of "Permission
 * requise"/"Position inconnue" (no cached fix yet)/"Erreur de
 * localisation" — check for those three literal strings rather than
 * assuming any non-null result is real coordinates. Real bug found the
 * hard way: both native bridges (`device:locate`) already implemented
 * this action, but no PHP-side class ever wrapped it — every other
 * device capability has one, this was the one exception.
 */
final class Location
{
    public static function fetchAction(string $outputField = 'location_out'): string
    {
        return "device:locate:{$outputField}";
    }

    public static function result(string $outputField = 'location_out'): ?string
    {
        return $_GET[$outputField] ?? null;
    }
}
