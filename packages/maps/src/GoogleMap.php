<?php

namespace Engine\Maps;

use Engine\Widget;

/**
 * Real interactive map (pan/zoom/marker) via the Google Maps JavaScript
 * API — needs an API key restricted (by HTTP referrer / package name) in
 * the Google Cloud Console, which is Google's own documented safe way to
 * use it client-side. Implemented against Google's current published API
 * docs; not exercised against a real Google Cloud project in this
 * environment (no key available here) — same honesty-about-confidence
 * rule as the payment gateways in Engine\Payments\.
 *
 * The API loads asynchronously and calls back into a global function, so
 * each instance gets its own uniquely-named callback to avoid collisions
 * if more than one GoogleMap is rendered on the same page.
 */
final class GoogleMap extends Widget
{
    public function __construct(
        private readonly string $apiKey,
        private readonly float $latitude,
        private readonly float $longitude,
        private readonly int $zoom = 15,
        private readonly string $classes = 'w-full h-64 rounded-xl',
    ) {
    }

    public static function make(
        string $apiKey,
        float $latitude,
        float $longitude,
        int $zoom = 15,
        string $classes = 'w-full h-64 rounded-xl',
    ): self {
        return new self($apiKey, $latitude, $longitude, $zoom, $classes);
    }

    public function render(): string
    {
        $id = 'gmap_' . substr(md5(uniqid('', true)), 0, 8);
        $callback = "phpxGoogleMapInit_{$id}";
        $key = htmlspecialchars($this->apiKey, ENT_QUOTES);
        $lat = htmlspecialchars((string) $this->latitude, ENT_QUOTES);
        $lng = htmlspecialchars((string) $this->longitude, ENT_QUOTES);
        $classes = htmlspecialchars($this->classes, ENT_QUOTES);

        return <<<HTML
            <div id="{$id}" class="{$classes}"></div>
            <script>
                function {$callback}() {
                    var map = new google.maps.Map(document.getElementById('{$id}'), {
                        center: {lat: {$lat}, lng: {$lng}},
                        zoom: {$this->zoom},
                    });
                    new google.maps.Marker({position: {lat: {$lat}, lng: {$lng}}, map: map});
                }
            </script>
            <script src="https://maps.googleapis.com/maps/api/js?key={$key}&callback={$callback}" async defer></script>
            HTML;
    }
}
