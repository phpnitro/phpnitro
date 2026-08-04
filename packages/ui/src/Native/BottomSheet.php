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

use Engine\Color;

/**
 * A modal panel anchored to the bottom edge, with a tap-outside-to-dismiss
 * scrim — built entirely on TWO existing primitives, no new
 * NativeCanvasView.kt code at all: Canvas::clientTabPanel() (ClientTabs'
 * own "open"/"closed" state lives on the client, zero network round-trip
 * to toggle — see that class's docblock) for the show/hide state itself,
 * and Fixed (beginFixed()/endFixed()) so the scrim+sheet paint relative to
 * the VIEWPORT rather than the scrollable body underneath, covering the
 * whole screen regardless of how far the user has scrolled.
 *
 * Open it from anywhere on the screen with a plain Tappable/Button whose
 * action is BottomSheet::openAction($key) — no special dispatch, that's
 * just "clientTab:{$key}:1" under the hood, the exact same action string
 * a ClientTabs header tap already produces.
 *
 * Known limitation: no slide-up animation (opens/closes instantly) and no
 * swipe-to-dismiss gesture — both are real future additions once there's
 * appetite for the client-side animation/gesture work they'd need
 * (WOULD need actual NativeCanvasView.kt changes, unlike everything else
 * here). Tap-outside and a real close button both work today.
 */
final class BottomSheet implements Widget
{
    public function __construct(
        private readonly string $key,
        private readonly Widget $content,
    ) {
    }

    public static function openAction(string $key): string
    {
        return "clientTab:{$key}:1";
    }

    public static function closeAction(string $key): string
    {
        return "clientTab:{$key}:0";
    }

    public function layout(Constraints $constraints): Size
    {
        // Contributes nothing to normal document flow — this is an
        // overlay, positioned against the full viewport in paint()
        // below via MediaQuery, not wherever a parent Column/Row would
        // otherwise have placed a child of this size.
        return Size::zero();
    }

    public function paint(Canvas $canvas, float $x, float $y): void
    {
        $screenWidth = MediaQuery::width();
        $screenHeight = MediaQuery::height();
        $screenSize = Constraints::tight($screenWidth, $screenHeight);

        $sheet = new Stack([
            new Tappable(
                new Container(width: $screenWidth, height: $screenHeight, background: Color::black()),
                self::closeAction($this->key),
            ),
            new Positioned(
                new Container(
                    new Padding(EdgeInsets::all(Tokens::SPACE_XL), $this->content),
                    width: $screenWidth,
                    background: Tokens::surface(),
                    radius: Tokens::RADIUS_LG,
                ),
                bottom: 0.0,
                left: 0.0,
            ),
        ]);
        $sheet->layout($screenSize);
        $openCanvas = new Canvas();
        $sheet->paint($openCanvas, 0.0, 0.0);

        $canvas->beginFixed();
        // Closed state first (initiallyActive) — an empty panel, nothing
        // drawn, no hit regions, so a freshly-loaded screen shows no
        // sheet and no scrim intercepting taps meant for the real
        // content underneath.
        $canvas->clientTabPanel($this->key, 0, true, 0.0, 0.0, [], []);
        $canvas->clientTabPanel($this->key, 1, false, 0.0, 0.0, $openCanvas->rawCommands(), $openCanvas->rawHitRegions());
        $canvas->endFixed();
    }
}
