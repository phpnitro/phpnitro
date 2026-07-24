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
 * Real geofencing (zone + enter/exit), via Play Services' GeofencingClient
 * — not BackgroundTask's periodic ping, and not a manual distance check.
 * Requires ACCESS_FINE_LOCATION (already requested at startup) and
 * ACCESS_BACKGROUND_LOCATION (declared in the manifest) for transitions to
 * fire while the app isn't in the foreground.
 */
final class Geofence
{
    public static function addOnClick(string $id, float $latitude, float $longitude, float $radiusMeters): string
    {
        return sprintf(
            "phpxDevice.addGeofence('%s', %s, %s, %s)",
            $id,
            $latitude,
            $longitude,
            $radiusMeters,
        );
    }

    public static function removeOnClick(string $id): string
    {
        return sprintf("phpxDevice.removeGeofence('%s')", $id);
    }
}
