<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine\Maps;

use Engine\Widget;

/**
 * Picks ONE map provider to render, whichever comes first in this priority
 * list with its key configured in $_ENV — see phpnitro.yml's `maps:`
 * section for the env var names. Same "check $_ENV in priority order" idiom
 * as CheckoutPage::selectPaymentWidget(). No key configured anywhere ->
 * OpenStreetMap (no configuration needed, always available).
 */
final class MapView
{
    public static function make(
        float $latitude,
        float $longitude,
        int $zoom = 15,
        string $classes = 'w-full h-64 rounded-xl',
    ): Widget {
        if (($token = $_ENV['MAPBOX_ACCESS_TOKEN'] ?? '') !== '') {
            return MapboxMap::make($token, $latitude, $longitude, $zoom, $classes);
        }

        if (($key = $_ENV['GOOGLE_MAPS_API_KEY'] ?? '') !== '') {
            return GoogleMap::make($key, $latitude, $longitude, $zoom, $classes);
        }

        return OsmMap::make($latitude, $longitude, $zoom, $classes);
    }
}
