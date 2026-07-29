<?php

namespace Engine\App;

use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\NativeAppBar;
use Engine\Native\NativeMapView;
use Engine\Native\NativeScaffold;
use Engine\Native\RenderContainer;
use Engine\Native\RenderFlex;
use Engine\Native\RenderNode;
use Engine\Native\RenderPadding;
use Engine\Native\RenderText;
use Engine\Native\Tokens;

/**
 * The native conversion of WidgetsMapsPage.php — same provider-resolution
 * logic as Engine\Maps\MapView::make() (Mapbox > Google Maps > OSM
 * fallback), but what actually renders here is a real, pannable/zoomable
 * org.osmdroid.views.MapView (NativeMapView, overlaid at the tapped rect —
 * see NativeRenderPocActivity's showMapOverlay()), not a static image.
 * osmdroid needs no API key, unlike Mapbox/Google Maps, so this works
 * regardless of what's configured in .env — same "OSM is the always-
 * available fallback" reasoning MapView::make() itself uses.
 */
final class NativeWidgetsMapsScreen
{
    public static function build(float $screenWidth, float $screenHeight): RenderNode
    {
        $configured = match (true) {
            ($_ENV['MAPBOX_ACCESS_TOKEN'] ?? '') !== '' => 'Mapbox (MAPBOX_ACCESS_TOKEN configuré)',
            ($_ENV['GOOGLE_MAPS_API_KEY'] ?? '') !== '' => 'Google Maps (GOOGLE_MAPS_API_KEY configuré)',
            default => 'OpenStreetMap (aucune clé configurée — repli par défaut)',
        };

        $contentWidth = $screenWidth - 2 * Tokens::SPACE_XL;

        // Paris, zoom 14 — same coordinates WidgetsMapsPage.php's MapView::make() demo uses.
        $lat = 48.8566;
        $lon = 2.3522;
        $zoom = 14;

        $body = new RenderContainer(
            new RenderPadding(
                EdgeInsets::all(Tokens::SPACE_XL),
                RenderFlex::column([
                    new RenderText(
                        "Fournisseur résolu par MapView::make() : {$configured}. La carte ci-dessous est un vrai osmdroid MapView — pan et pincer-zoomer fonctionnent.",
                        Tokens::TEXT_BODY_SMALL,
                        Tokens::inkMuted()->toHex(),
                    ),
                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_XL),
                        new NativeMapView($lat, $lon, $zoom, $contentWidth),
                    ),
                ], crossAxisAlignment: CrossAxisAlignment::STRETCH),
            ),
            width: $screenWidth,
            background: Tokens::surfaceMuted(),
        );

        return new NativeScaffold(
            $body,
            $screenWidth,
            $screenHeight,
            appBar: new NativeAppBar($screenWidth, 'Cartes', backAction: 'back'),
        );
    }
}
