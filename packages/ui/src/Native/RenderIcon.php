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
 * A fixed-size square icon — one of NativeCanvasView's IconPainter names
 * (arrow_back, edit, check, check_circle, document, plus, chevron_up,
 * chevron_down, hourglass, shield, warning, info, close). Not an SVG/font
 * glyph pipeline: each icon is a handful of Canvas line/arc primitives
 * drawn natively, which is what makes this safe to hand-roll without a
 * vector asset pipeline while still looking like a real icon, not a
 * placeholder box.
 */
final class RenderIcon implements RenderNode
{
    public function __construct(
        private readonly string $name,
        private readonly float $size = 24.0,
        private readonly string $color = '#111827',
        private readonly float $strokeWidth = 2.0,
    ) {
    }

    public function layout(Constraints $constraints): Size
    {
        return $constraints->constrain(new Size($this->size, $this->size));
    }

    public function paint(NativeCanvas $canvas, float $x, float $y): void
    {
        $canvas->icon($x, $y, $this->size, $this->name, $this->color, $this->strokeWidth);
    }
}
