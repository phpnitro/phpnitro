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
 * Flutter's CircleAvatar for a network image — Image already draws a
 * true circle whenever it's given a square box and a radius of half that
 * box (NativeCanvasView.kt's drawImageCommand() clips via
 * canvas.drawRoundRect(rect, radius, radius, ...), which degenerates to a
 * circle exactly when radius == width/2 == height/2), so this widget adds
 * no new native capability — it's the same convenience IconCircle
 * already is for an icon-in-a-circle: a friendly, discoverable name for a
 * shape callers would otherwise have to remember to compose by hand.
 */
final class ImageCircle implements Widget
{
    private readonly Image $content;

    public function __construct(
        string $url,
        private readonly float $diameter = 40.0,
    ) {
        $this->content = new Image($url, $diameter, $diameter, $diameter / 2);
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
