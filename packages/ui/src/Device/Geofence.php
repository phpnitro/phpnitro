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
 * Registers/removes a circular geofence via GeofencingClient (see
 * NativeDeviceBridge.kt's addGeofence()/removeGeofence(), the same
 * GeofenceReceiver both rendering paths share) — an action-string
 * builder, not a widget: attach Geofence::addAction()/
 * Geofence::removeAction() to any Button.
 *
 * ACCESS_FINE_LOCATION is only ever checked, never requested — pair
 * with Permission::requestAction('location') first if it might not be
 * granted yet (a missing grant makes addAction() a silent no-op). $id
 * is a caller-chosen identifier — the same one passed to addAction()
 * must be passed to removeAction() to remove that specific zone. No
 * result field for either — a geofence transition (enter/exit) fires a
 * broadcast to GeofenceReceiver, not back into this request/response
 * cycle.
 */
final class Geofence
{
    public static function addAction(string $id, float $latitude, float $longitude, float $radiusMeters): string
    {
        return 'device:geofenceadd:' . rawurlencode($id) . ":{$latitude}:{$longitude}:{$radiusMeters}";
    }

    public static function removeAction(string $id): string
    {
        return 'device:geofenceremove:' . rawurlencode($id);
    }
}
