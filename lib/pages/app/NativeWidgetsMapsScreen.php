<?php

namespace Engine\App;

use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\NativeAppBar;
use Engine\Native\NativeButton;
use Engine\Native\NativeScaffold;
use Engine\Native\RenderContainer;
use Engine\Native\RenderFlex;
use Engine\Native\RenderImage;
use Engine\Native\RenderNode;
use Engine\Native\RenderPadding;
use Engine\Native\RenderText;
use Engine\Native\Tokens;

/**
 * The native conversion of WidgetsMapsPage.php — same provider-resolution
 * logic as Engine\Maps\MapView::make() (Mapbox > Google Maps > OSM
 * fallback), but what actually renders here is a single real OpenStreetMap
 * tile image (via NativeCanvas::image()'s existing async HTTP loader), not
 * a mockup rectangle. That's an honest scope: a real map tile, centered on
 * the right coordinates, but NOT a pannable/zoomable interactive map.
 *
 * A genuinely interactive native map (Mapbox Android SDK / Google Maps
 * Android SDK / osmdroid) is real, additional work this doesn't attempt —
 * each needs its own Gradle dependency, API key wiring, and a real
 * android.view.View (MapView) embedded via the same FrameLayout-overlay
 * technique NativeTextField's EditText uses, with its own onResume/
 * onPause lifecycle. Deferred, same as VideoPlayer/GoogleTranslate.
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

        // Paris, zoom 14 — same coordinates WidgetsMapsPage.php's
        // MapView::make() demo uses. Standard Web Mercator lat/lon ->
        // tile x/y conversion (the same math every slippy-map tile client
        // uses) since there's no map SDK resolving this for us here.
        $lat = 48.8566;
        $lon = 2.3522;
        $zoom = 14;
        $tileX = (int) floor(($lon + 180) / 360 * (2 ** $zoom));
        $tileY = (int) floor((1 - log(tan(deg2rad($lat)) + 1 / cos(deg2rad($lat))) / M_PI) / 2 * (2 ** $zoom));
        $tileUrl = "https://tile.openstreetmap.org/{$zoom}/{$tileX}/{$tileY}.png";

        $body = new RenderContainer(
            new RenderPadding(
                EdgeInsets::all(Tokens::SPACE_XL),
                RenderFlex::column([
                    new RenderText(
                        "Fournisseur résolu par MapView::make() : {$configured}. Aperçu ci-dessous : une vraie tuile OpenStreetMap (pas interactive — voir le docblock de cet écran).",
                        Tokens::TEXT_BODY_SMALL,
                        Tokens::inkMuted()->toHex(),
                    ),
                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_XL),
                        new RenderImage($tileUrl, $contentWidth, $contentWidth, radius: Tokens::RADIUS_LG),
                    ),
                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_XL),
                        new NativeButton('Carte interactive (WebView)', 'webview:/widgets/maps', background: Tokens::surfaceMuted(), foreground: Tokens::ink()),
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
