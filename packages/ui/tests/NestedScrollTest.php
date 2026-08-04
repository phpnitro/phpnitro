<?php

namespace Engine\Tests;

use Engine\Native\Canvas;
use Engine\Native\Constraints;
use Engine\Native\Flex;
use Engine\Native\NestedScroll;
use Engine\Native\SizedBox;
use PHPUnit\Framework\TestCase;

final class NestedScrollTest extends TestCase
{
    public function testReportsTheGivenViewportHeightNotTheContentHeight(): void
    {
        $tall = Flex::column([
            new SizedBox(width: 0.0, height: 800.0),
        ]);
        $scroll = new NestedScroll('demo', $tall, 200.0);

        $size = $scroll->layout(new Constraints(0, 360, 0, Constraints::INFINITY));

        $this->assertSame(200.0, $size->height);
    }

    public function testEmitsAVScrollCommandWithTheRealContentHeight(): void
    {
        $tall = Flex::column([
            new SizedBox(width: 0.0, height: 800.0),
        ]);
        $scroll = new NestedScroll('demo', $tall, 200.0);
        $scroll->layout(new Constraints(0, 360, 0, Constraints::INFINITY));

        $canvas = new Canvas();
        $scroll->paint($canvas, 10.0, 20.0);
        $decoded = json_decode($canvas->toJson(), true);

        $vScroll = null;
        foreach ($decoded['commands'] as $command) {
            if (($command['type'] ?? null) === 'vScroll') {
                $vScroll = $command;
            }
        }

        $this->assertNotNull($vScroll);
        $this->assertSame('demo', $vScroll['key']);
        $this->assertEqualsWithDelta(10.0, $vScroll['x'], 0.01);
        $this->assertEqualsWithDelta(20.0, $vScroll['y'], 0.01);
        $this->assertEqualsWithDelta(200.0, $vScroll['height'], 0.01);
        $this->assertEqualsWithDelta(800.0, $vScroll['contentHeight'], 0.01);
    }
}
