<?php

namespace Engine\Tests;

use Engine\Native\BottomSheet;
use Engine\Native\Canvas;
use Engine\Native\Constraints;
use Engine\Native\Flex;
use Engine\Native\MediaQuery;
use Engine\Native\Text;
use Engine\Native\Tokens;
use PHPUnit\Framework\TestCase;

/**
 * Regression test for a real device bug: the sheet card used to size
 * itself to the FULL screen height instead of hugging its own content
 * (Flex::column's default mainAxisSize.max behavior kicking in because
 * the card was laid out against a bounded, not infinite, height
 * constraint — see BottomSheet.php's own paint() docblock). Caught on a
 * real device, not by php -l or a type checker, since the JSON was
 * perfectly valid — just describing a solid white full-screen rect
 * instead of a small card anchored to the bottom.
 */
final class BottomSheetTest extends TestCase
{
    protected function setUp(): void
    {
        Tokens::init(false);
        MediaQuery::init(360.0, 780.0);
    }

    public function testOpenPanelCardHugsItsContentInsteadOfFillingTheScreen(): void
    {
        // A Flex::column, not a bare Text — Flex's default mainAxisSize
        // (fill the ENTIRE main-axis constraint whenever it's bounded,
        // matching Flutter's own Column default) is exactly what the
        // real bug hinged on; a bare Text never exhibited it, so a
        // regression test built on one alone wouldn't have caught this.
        $sheet = new BottomSheet('demo', Flex::column([
            new Text('Short content', Tokens::TEXT_BODY, Tokens::ink()->toHex()),
        ]));
        $sheet->layout(new Constraints(0, 360.0, 0, Constraints::INFINITY));
        $canvas = new Canvas();
        $sheet->paint($canvas, 0, 0);

        $decoded = json_decode($canvas->toJson(), true);
        $openPanel = null;
        foreach ($decoded['commands'] as $command) {
            if (($command['type'] ?? null) === 'clientPanel' && ($command['key'] ?? null) === 'demo' && ($command['index'] ?? null) === 1) {
                $openPanel = $command;
            }
        }
        $this->assertNotNull($openPanel, 'the open (index 1) clientPanel entry should exist');

        // Widest rect (the card) and the full-viewport rect (the scrim) —
        // the small grab-handle bar is also a "rect" command now, but at
        // 36dp wide it never competes with either.
        $rects = array_values(array_filter($openPanel['commands'], static fn (array $c): bool => ($c['type'] ?? null) === 'rect'));
        $this->assertGreaterThanOrEqual(3, count($rects), 'expected at least a scrim rect, the card rect, and the grab handle');

        $scrim = current(array_filter($rects, static fn (array $c): bool => $c['height'] > 700.0));
        $card = current(array_filter($rects, static fn (array $c): bool => $c['width'] > 300.0 && $c['height'] < 700.0));
        $this->assertNotFalse($scrim, 'expected a full-viewport scrim rect');
        $this->assertNotFalse($card, 'expected a wide card rect distinct from the scrim');

        $this->assertEqualsWithDelta(780.0, $scrim['height'], 0.01, 'the scrim covers the full viewport height');
        $this->assertLessThan(200.0, $card['height'], 'the card must hug its own short content, not fill the screen');
        $this->assertGreaterThan(500.0, $card['y'], 'the card must be anchored near the bottom of the screen');
    }
}
