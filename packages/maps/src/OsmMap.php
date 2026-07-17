<?php

namespace Engine\Maps;

use Engine\Widget;

/**
 * Real interactive map (pan/zoom/marker) via Leaflet.js + OpenStreetMap
 * tiles — no API key, no billing account, works immediately. Unlike the
 * bare iframe embed this replaces, this one is a genuine map widget: same
 * capability tier as MapboxMap/GoogleMap, just with no configuration step.
 */
final class OsmMap extends Widget
{
    public function __construct(
        private readonly float $latitude,
        private readonly float $longitude,
        private readonly int $zoom = 15,
        private readonly string $classes = 'w-full h-64 rounded-xl',
    ) {
    }

    public static function make(
        float $latitude,
        float $longitude,
        int $zoom = 15,
        string $classes = 'w-full h-64 rounded-xl',
    ): self {
        return new self($latitude, $longitude, $zoom, $classes);
    }

    public function render(): string
    {
        $id = 'osm_' . substr(md5(uniqid('', true)), 0, 8);
        $lat = htmlspecialchars((string) $this->latitude, ENT_QUOTES);
        $lng = htmlspecialchars((string) $this->longitude, ENT_QUOTES);
        $classes = htmlspecialchars($this->classes, ENT_QUOTES);

        return <<<HTML
            <div id="{$id}" class="{$classes}"></div>
            <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
            <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
            <script>
                (function () {
                    var map = L.map('{$id}').setView([{$lat}, {$lng}], {$this->zoom});
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                        maxZoom: 19,
                    }).addTo(map);
                    L.marker([{$lat}, {$lng}]).addTo(map);
                })();
            </script>
            HTML;
    }
}
