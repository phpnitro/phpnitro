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

final class RenderCenter implements RenderNode
{
    private float $childX = 0.0;
    private float $childY = 0.0;

    public function __construct(
        private readonly RenderNode $child,
    ) {
    }

    public function layout(Constraints $constraints): Size
    {
        $childSize = $this->child->layout($constraints->loosen());

        $width = $constraints->hasBoundedWidth() ? $constraints->maxWidth : $childSize->width;
        $height = $constraints->hasBoundedHeight() ? $constraints->maxHeight : $childSize->height;

        $this->childX = ($width - $childSize->width) / 2;
        $this->childY = ($height - $childSize->height) / 2;

        return $constraints->constrain(new Size($width, $height));
    }

    public function paint(NativeCanvas $canvas, float $x, float $y): void
    {
        $this->child->paint($canvas, $x + $this->childX, $y + $this->childY);
    }
}
