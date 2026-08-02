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
 * Forces a fixed size (used bare as fixed-size spacing, or wrapping a child
 * to override the size it would otherwise have picked).
 */
final class RenderSizedBox implements RenderNode
{
    public function __construct(
        private readonly float $width,
        private readonly float $height,
        private readonly ?RenderNode $child = null,
    ) {
    }

    public function layout(Constraints $constraints): Size
    {
        $this->child?->layout(Constraints::tight($this->width, $this->height));

        return $constraints->constrain(new Size($this->width, $this->height));
    }

    public function paint(NativeCanvas $canvas, float $x, float $y): void
    {
        $this->child?->paint($canvas, $x, $y);
    }
}
