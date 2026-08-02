<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine\Native;

/**
 * The native-tree equivalent of Engine\Maps\MapView — a real, pannable/
 * zoomable org.osmdroid.views.MapView overlaid at the tapped rect (same
 * "no DOM element to attach to" idiom TextField's EditText and
 * VideoPlayer's VideoView already use), not the single static
 * OpenStreetMap tile image NativeWidgetsMapsScreen.php showed before this.
 * osmdroid needs no API key (unlike Mapbox/Google Maps), so this is
 * always available regardless of what's configured in .env.
 */
final class MapView implements Widget
{
    private readonly Widget $content;

    public function __construct(float $latitude, float $longitude, int $zoom, float $width, float $height = 240.0)
    {
        $box = new Container(
            new Center(Flex::row([
                new Icon('map', 32.0, Tokens::ink()->toHex()),
                new Padding(EdgeInsets::only(left: Tokens::SPACE_SM), new Text('Ouvrir la carte interactive', Tokens::TEXT_BODY, Tokens::ink()->toHex(), bold: true)),
            ], crossAxisAlignment: CrossAxisAlignment::CENTER)),
            width: $width,
            height: $height,
            background: Tokens::surfaceMuted(),
            radius: Tokens::RADIUS_LG,
        );

        $this->content = new Tappable($box, "map:open:{$latitude}:{$longitude}:{$zoom}");
    }

    public function layout(Constraints $constraints): Size
    {
        return $this->content->layout($constraints);
    }

    public function paint(Canvas $canvas, float $x, float $y): void
    {
        $this->content->paint($canvas, $x, $y);
    }
}
