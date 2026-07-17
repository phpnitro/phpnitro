<?php

namespace Engine\Maps\Tests;

use Engine\Maps\GoogleMap;
use Engine\Maps\MapboxMap;
use Engine\Maps\MapView;
use Engine\Maps\OsmMap;
use PHPUnit\Framework\TestCase;

final class MapsTest extends TestCase
{
    protected function setUp(): void
    {
        unset($_ENV['MAPBOX_ACCESS_TOKEN'], $_ENV['GOOGLE_MAPS_API_KEY']);
    }

    public function testOsmMapRendersLeafletWithCoordinatesAndMarker(): void
    {
        $html = OsmMap::make(48.8566, 2.3522, 14)->render();

        $this->assertStringContainsString('leaflet.js', $html);
        $this->assertStringContainsString('setView([48.8566, 2.3522], 14)', $html);
        $this->assertStringContainsString('L.marker([48.8566, 2.3522])', $html);
    }

    public function testMapboxMapEscapesAccessTokenAndUsesCoordinates(): void
    {
        $html = MapboxMap::make('<script>', 48.8566, 2.3522)->render();

        $this->assertStringContainsString('mapbox-gl.js', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('center: [2.3522, 48.8566]', $html);
    }

    public function testGoogleMapUsesAUniqueCallbackPerInstance(): void
    {
        $first = GoogleMap::make('key', 48.8566, 2.3522)->render();
        $second = GoogleMap::make('key', 48.8566, 2.3522)->render();

        preg_match('/callback=(phpxGoogleMapInit_gmap_[a-f0-9]+)/', $first, $firstMatch);
        preg_match('/callback=(phpxGoogleMapInit_gmap_[a-f0-9]+)/', $second, $secondMatch);

        $this->assertNotSame([], $firstMatch);
        $this->assertNotSame($firstMatch[1], $secondMatch[1]);
    }

    public function testMapViewFallsBackToOsmWithoutAnyKeyConfigured(): void
    {
        $this->assertInstanceOf(OsmMap::class, MapView::make(48.8566, 2.3522));
    }

    public function testMapViewPrefersMapboxOverGoogleWhenBothConfigured(): void
    {
        $_ENV['MAPBOX_ACCESS_TOKEN'] = 'token';
        $_ENV['GOOGLE_MAPS_API_KEY'] = 'key';

        $this->assertInstanceOf(MapboxMap::class, MapView::make(48.8566, 2.3522));
    }

    public function testMapViewFallsBackToGoogleWhenOnlyItIsConfigured(): void
    {
        $_ENV['GOOGLE_MAPS_API_KEY'] = 'key';

        $this->assertInstanceOf(GoogleMap::class, MapView::make(48.8566, 2.3522));
    }
}
