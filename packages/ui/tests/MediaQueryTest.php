<?php

namespace Engine\Tests;

use Engine\Native\Constraints;
use Engine\Native\MediaQuery;
use Engine\Native\NativeCanvas;
use Engine\Native\NativeImageCircle;
use PHPUnit\Framework\TestCase;

final class MediaQueryTest extends TestCase
{
    public function testInitSetsWidthAndHeight(): void
    {
        MediaQuery::init(414.0, 896.0);

        $this->assertSame(414.0, MediaQuery::width());
        $this->assertSame(896.0, MediaQuery::height());
        $this->assertSame(414.0, MediaQuery::size()->width);
        $this->assertSame(896.0, MediaQuery::size()->height);
    }

    public function testIsLandscape(): void
    {
        MediaQuery::init(800.0, 400.0);
        $this->assertTrue(MediaQuery::isLandscape());

        MediaQuery::init(400.0, 800.0);
        $this->assertFalse(MediaQuery::isLandscape());
    }

    public function testReadableFromDeepInsideATreeWithNoExplicitParameter(): void
    {
        MediaQuery::init(360.0, 720.0);

        // The whole point: a widget several constructors deep, built with
        // no $screenWidth/$screenHeight ever passed to it, still learns
        // the viewport size — exactly the case an explicit build($width,
        // $height) parameter can't cover once a reusable widget is nested
        // more than one level under a screen's own build().
        $probe = new class implements \Engine\Native\RenderNode {
            public float $observedWidth = 0.0;

            public function layout(Constraints $constraints): \Engine\Native\Size
            {
                $this->observedWidth = MediaQuery::width();

                return $constraints->constrain(new \Engine\Native\Size(0, 0));
            }

            public function paint(NativeCanvas $canvas, float $x, float $y): void
            {
            }
        };

        $probe->layout(new Constraints(0, 1000, 0, Constraints::INFINITY));
        $this->assertSame(360.0, $probe->observedWidth);
    }
}
