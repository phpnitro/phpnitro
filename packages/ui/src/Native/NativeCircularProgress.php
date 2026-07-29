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
 * The native-tree equivalent of Engine\CircularProgress — a full-sweep
 * track arc plus a partial-sweep filled arc on top, both via
 * NativeCanvas::arc(). Starting at -90° (12 o'clock) matches the HTML
 * widget's `-rotate-90` SVG trick without needing a canvas-level rotation.
 */
final class NativeCircularProgress implements RenderNode
{
    private readonly float $strokeWidth;

    public function __construct(
        private readonly float $percent,
        private readonly float $size = 64.0,
        private readonly ?Color $trackColor = null,
        private readonly ?Color $color = null,
    ) {
        $this->strokeWidth = max(2.0, $size / 16);
    }

    public function layout(Constraints $constraints): Size
    {
        return $constraints->constrain(new Size($this->size, $this->size));
    }

    public function paint(NativeCanvas $canvas, float $x, float $y): void
    {
        $clamped = max(0.0, min(1.0, $this->percent));
        $center = $this->size / 2;
        $radius = $center - $this->strokeWidth / 2;

        $canvas->arc($x + $center, $y + $center, $radius, 0.0, 360.0, ($this->trackColor ?? Tokens::surfaceMuted())->toHex(), $this->strokeWidth);
        if ($clamped > 0.0) {
            $canvas->arc($x + $center, $y + $center, $radius, -90.0, 360.0 * $clamped, ($this->color ?? Tokens::ink())->toHex(), $this->strokeWidth);
        }
    }
}
