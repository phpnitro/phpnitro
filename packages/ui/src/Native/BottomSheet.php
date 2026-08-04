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
 * Open/close both animate (slide up/down, riding NativeCanvasView.kt's
 * setClientTab() — a sheet key gets a tween instead of an instant
 * snap, keyed off its presence in sheetHandleRegions) and the small grab
 * bar at the top of the card is a REAL continuous drag: dragging it down
 * tracks the finger live (Canvas::sheetHandle(), the same "PHP never
 * sees the gesture, only its outcome" split as Dismissible), springing
 * back to open if released short of the close threshold, finishing the
 * slide-down and flipping to closed otherwise. Tap-outside and a real
 * close button both still work exactly as before, now animated too.
 */
final class BottomSheet implements Widget
{
    private const HANDLE_HIT_HEIGHT = 32.0;

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

        // Flex::column (like Flutter's Column) fills its ENTIRE main-axis
        // constraint whenever that constraint is bounded — its default
        // mainAxisSize.max behavior, correct for every screen's own root
        // layout (built against Constraints::INFINITY so content hugs
        // naturally) but wrong here: laying $paddedContent out directly
        // against the Stack's bounded $screenHeight would make it claim
        // the FULL screen height instead of hugging its own text/button,
        // turning the "card" into a second full-bleed white screen with
        // no visible scrim above it — caught on a real device, not
        // something php -l or a unit test would have surfaced. Measuring
        // it first against Constraints::INFINITY (mirroring how a root
        // screen measures itself) gets its true intrinsic height, then an
        // explicit height: below pins the real Container to exactly that.
        // A small grab bar, like Material's own modal bottom sheet — purely
        // decorative on its own (the draggable AREA is the wider
        // self::HANDLE_HIT_HEIGHT strip registered via sheetHandle()
        // below, not just these 4dp of visible bar), but it's what tells a
        // user "you can drag this" at a glance.
        $handle = new Padding(
            EdgeInsets::only(top: Tokens::SPACE_SM, bottom: Tokens::SPACE_SM),
            new Center(new Container(width: 36.0, height: 4.0, background: Tokens::border(), radius: 2.0)),
        );
        $cardBody = Flex::column([
            $handle,
            new Padding(EdgeInsets::only(left: Tokens::SPACE_XL, right: Tokens::SPACE_XL, bottom: Tokens::SPACE_XL), $this->content),
        ]);
        // See setTransition()'s neighbor docblock note above for why this
        // must be measured against Constraints::INFINITY first rather than
        // laid out directly against the Stack's bounded $screenHeight.
        $cardHeight = $cardBody->layout(new Constraints(0, $screenWidth, 0, Constraints::INFINITY))->height;

        $sheet = new Stack([
            new Tappable(
                new Container(width: $screenWidth, height: $screenHeight, background: Color::black()),
                self::closeAction($this->key),
            ),
            new Positioned(
                new Container(
                    $cardBody,
                    width: $screenWidth,
                    height: $cardHeight,
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
        $canvas->sheetHandle(
            $this->key,
            0.0,
            $screenHeight - $cardHeight,
            $screenWidth,
            self::HANDLE_HIT_HEIGHT,
            $cardHeight,
            self::closeAction($this->key),
        );
        // Closed state first (initiallyActive) — an empty panel, nothing
        // drawn, no hit regions, so a freshly-loaded screen shows no
        // sheet and no scrim intercepting taps meant for the real
        // content underneath.
        $canvas->clientTabPanel($this->key, 0, true, 0.0, 0.0, [], []);
        $canvas->clientTabPanel($this->key, 1, false, 0.0, 0.0, $openCanvas->rawCommands(), $openCanvas->rawHitRegions());
        $canvas->endFixed();
    }
}
