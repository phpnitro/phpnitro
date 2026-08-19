<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine\Tests\Golden;

use Engine\Native\CustomPaint;
use Engine\Native\Flex;
use Engine\Native\HorizontalScroll;
use Engine\Native\Image;
use Engine\Native\Spinner;

/**
 * Closes a real gap found while building the shared Rust rendering core
 * (rust/phpnitro-render): none of the existing golden fixtures exercise
 * `circle`, `image`, `spinner`, or `hScroll` commands, so Rust's own
 * test suite had nothing real to decode/render for these 4 types.
 * Generated the same way as every other fixture — never hand-typed —
 * via `GOLDEN_UPDATE=1 vendor/bin/phpunit --testsuite ui --filter Golden`.
 */
final class NewCommandsGoldenTest extends GoldenTestCase
{
    public function testCircleBasic(): void
    {
        $this->assertMatchesGolden(
            'circle_basic',
            CustomPaint::make(60.0, 60.0)->circle(30.0, 30.0, 24.0, '#22C55E'),
        );
    }

    public function testImageNetworkAndDataUri(): void
    {
        $this->assertMatchesGolden(
            'image_network_and_data_uri',
            Flex::row([
                new Image('https://example.com/photo.jpg', 80.0, 80.0),
                new Image(
                    'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
                    80.0,
                    80.0,
                    radius: 12.0,
                ),
            ]),
        );
    }

    public function testSpinnerBasic(): void
    {
        $this->assertMatchesGolden('spinner_basic', new Spinner(32.0));
    }

    public function testHorizontalScrollBasic(): void
    {
        $this->assertMatchesGolden(
            'hscroll_basic',
            new HorizontalScroll('carousel', [
                CustomPaint::make(60.0, 60.0)->rect(0.0, 0.0, 60.0, 60.0, '#EF4444'),
                CustomPaint::make(60.0, 60.0)->rect(0.0, 0.0, 60.0, 60.0, '#3B82F6'),
                CustomPaint::make(60.0, 60.0)->rect(0.0, 0.0, 60.0, 60.0, '#22C55E'),
            ], gap: 8.0),
        );
    }
}
