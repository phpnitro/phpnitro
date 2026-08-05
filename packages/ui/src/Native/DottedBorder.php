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
 * Wraps any child in a dashed rectangle — Container's own borderColor/
 * borderWidth only ever draws a SOLID border (a single native rect
 * command with border fields), there's no dash-pattern flag to set. This
 * computes the dash segments in PHP instead (one Canvas::line() call per
 * segment, around all four edges) rather than adding a new draw-command
 * type — a genuinely new native primitive would need a matching
 * NativeCanvasView.kt handler; decomposing into lines this framework
 * already draws needs none.
 */
final class DottedBorder implements Widget
{
    private Size $size;

    public function __construct(
        private readonly Widget $child,
        private readonly string $color = '#9CA3AF',
        private readonly float $strokeWidth = 1.5,
        private readonly float $dashLength = 6.0,
        private readonly float $gapLength = 4.0,
    ) {
        $this->size = Size::zero();
    }

    public function layout(Constraints $constraints): Size
    {
        $this->size = $this->child->layout($constraints);

        return $this->size;
    }

    public function paint(Canvas $canvas, float $x, float $y): void
    {
        $this->child->paint($canvas, $x, $y);

        $width = $this->size->width;
        $height = $this->size->height;
        $this->drawDashedLine($canvas, $x, $y, $x + $width, $y);
        $this->drawDashedLine($canvas, $x, $y + $height, $x + $width, $y + $height);
        $this->drawDashedLine($canvas, $x, $y, $x, $y + $height);
        $this->drawDashedLine($canvas, $x + $width, $y, $x + $width, $y + $height);
    }

    private function drawDashedLine(Canvas $canvas, float $x1, float $y1, float $x2, float $y2): void
    {
        $length = sqrt(($x2 - $x1) ** 2 + ($y2 - $y1) ** 2);
        if ($length <= 0.0) {
            return;
        }

        $dirX = ($x2 - $x1) / $length;
        $dirY = ($y2 - $y1) / $length;
        $step = $this->dashLength + $this->gapLength;

        for ($covered = 0.0; $covered < $length; $covered += $step) {
            $segmentEnd = min($covered + $this->dashLength, $length);
            $canvas->line(
                $x1 + $dirX * $covered,
                $y1 + $dirY * $covered,
                $x1 + $dirX * $segmentEnd,
                $y1 + $dirY * $segmentEnd,
                $this->color,
                $this->strokeWidth,
            );
        }
    }
}
