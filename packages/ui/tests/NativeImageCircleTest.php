<?php

namespace Engine\Tests;

use Engine\Native\Constraints;
use Engine\Native\NativeCanvas;
use Engine\Native\NativeImageCircle;
use PHPUnit\Framework\TestCase;

final class NativeImageCircleTest extends TestCase
{
    public function testEmitsASquareImageCommandWithHalfRadius(): void
    {
        $node = new NativeImageCircle('https://example.test/avatar.jpg', diameter: 48.0);
        $node->layout(new Constraints(0, 1000, 0, Constraints::INFINITY));

        $canvas = new NativeCanvas();
        $node->paint($canvas, 10.0, 20.0);
        $commands = json_decode($canvas->toJson(), true)['commands'];

        $this->assertCount(1, $commands);
        $command = $commands[0];
        $this->assertSame('image', $command['type']);
        // json_decode() hands back an int for a whole-number JSON value
        // (48 not 48.0), so these compare loosely rather than assertSame.
        $this->assertEquals(48.0, $command['width']);
        $this->assertEquals(48.0, $command['height']);
        // Half the box's side — NativeCanvasView.kt's drawRoundRect(rect,
        // radius, radius, ...) only degenerates into a true circle (not
        // just rounded corners) at exactly this ratio.
        $this->assertEquals(24.0, $command['radius']);
    }
}
