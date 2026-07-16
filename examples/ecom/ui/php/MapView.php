<?php

namespace Engine;

/**
 * Embeds an OpenStreetMap view — no API key, no billing account, works
 * immediately. Good enough for "show a location"; swap for a native
 * MapLibre/Google Maps SDK bridge later if you need real interactivity
 * (custom markers, routing) beyond what an iframe can do.
 */
final class MapView extends Widget
{
    public function __construct(
        private readonly float $latitude,
        private readonly float $longitude,
        private readonly int $zoom = 15,
        private readonly string $classes = 'w-full h-64 rounded-xl border-0',
    ) {
    }

    public static function make(
        float $latitude,
        float $longitude,
        int $zoom = 15,
        string $classes = 'w-full h-64 rounded-xl border-0',
    ): self {
        return new self($latitude, $longitude, $zoom, $classes);
    }

    public function render(): string
    {
        // Rough bbox from zoom: each zoom level roughly halves the visible span.
        $span = 360 / (2 ** $this->zoom) * 256;
        $left = $this->longitude - $span;
        $right = $this->longitude + $span;
        $bottom = $this->latitude - $span;
        $top = $this->latitude + $span;

        $src = sprintf(
            'https://www.openstreetmap.org/export/embed.html?bbox=%F,%F,%F,%F&layer=mapnik&marker=%F,%F',
            $left,
            $bottom,
            $right,
            $top,
            $this->latitude,
            $this->longitude,
        );

        return sprintf(
            '<iframe src="%s" class="%s" loading="lazy"></iframe>',
            htmlspecialchars($src, ENT_QUOTES),
            htmlspecialchars($this->classes, ENT_QUOTES),
        );
    }
}
