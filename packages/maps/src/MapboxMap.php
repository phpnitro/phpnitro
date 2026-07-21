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
 * Real interactive map (pan/zoom/marker) via Mapbox GL JS v3 — needs a
 * Mapbox access token (safe to expose client-side: that's how Mapbox's own
 * public tokens are meant to be used, unlike a payment gateway's secret
 * key). Implemented against Mapbox's current published API docs; not
 * exercised against a real Mapbox account in this environment (no sandbox
 * token available here) — same honesty-about-confidence rule as the
 * payment gateways in Engine\Payments\.
 */
final class MapboxMap extends Widget
{
    public function __construct(
        private readonly string $accessToken,
        private readonly float $latitude,
        private readonly float $longitude,
        private readonly int $zoom = 15,
        private readonly string $classes = 'w-full h-64 rounded-xl',
    ) {
    }

    public static function make(
        string $accessToken,
        float $latitude,
        float $longitude,
        int $zoom = 15,
        string $classes = 'w-full h-64 rounded-xl',
    ): self {
        return new self($accessToken, $latitude, $longitude, $zoom, $classes);
    }

    public function render(): string
    {
        $id = 'mbx_' . substr(md5(uniqid('', true)), 0, 8);
        $token = htmlspecialchars($this->accessToken, ENT_QUOTES);
        $lat = htmlspecialchars((string) $this->latitude, ENT_QUOTES);
        $lng = htmlspecialchars((string) $this->longitude, ENT_QUOTES);
        $classes = htmlspecialchars($this->classes, ENT_QUOTES);

        return <<<HTML
            <div id="{$id}" class="{$classes}"></div>
            <link rel="stylesheet" href="https://api.mapbox.com/mapbox-gl-js/v3.7.0/mapbox-gl.css">
            <script>
                (function () {
                    function init() {
                        mapboxgl.accessToken = '{$token}';
                        var map = new mapboxgl.Map({
                            container: '{$id}',
                            style: 'mapbox://styles/mapbox/streets-v12',
                            center: [{$lng}, {$lat}],
                            zoom: {$this->zoom},
                        });
                        new mapboxgl.Marker().setLngLat([{$lng}, {$lat}]).addTo(map);
                    }

                    // See OsmMap for why this loads mapbox-gl.js dynamically
                    // with an onload callback instead of a plain <script
                    // src> tag followed by an inline script.
                    if (window.mapboxgl) {
                        init();
                        return;
                    }
                    var script = document.createElement('script');
                    script.src = 'https://api.mapbox.com/mapbox-gl-js/v3.7.0/mapbox-gl.js';
                    script.onload = init;
                    document.head.appendChild(script);
                })();
            </script>
            HTML;
    }
}
