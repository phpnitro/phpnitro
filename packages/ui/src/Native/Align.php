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
 * RenderCenter generalized to any Alignment — RenderCenter stays as its
 * own class (it's the overwhelmingly common case and reads clearer at call
 * sites) rather than becoming `RenderAlign(..., Alignment::CENTER)`
 * everywhere.
 */
final class RenderAlign implements RenderNode
{
    private float $childX = 0.0;
    private float $childY = 0.0;

    public function __construct(
        private readonly RenderNode $child,
        private readonly Alignment $alignment,
    ) {
    }

    public function layout(Constraints $constraints): Size
    {
        $childSize = $this->child->layout($constraints->loosen());

        $width = $constraints->hasBoundedWidth() ? $constraints->maxWidth : $childSize->width;
        $height = $constraints->hasBoundedHeight() ? $constraints->maxHeight : $childSize->height;

        $this->childX = match ($this->alignment) {
            Alignment::TOP_LEFT, Alignment::CENTER_LEFT, Alignment::BOTTOM_LEFT => 0.0,
            Alignment::TOP_CENTER, Alignment::CENTER, Alignment::BOTTOM_CENTER => ($width - $childSize->width) / 2,
            Alignment::TOP_RIGHT, Alignment::CENTER_RIGHT, Alignment::BOTTOM_RIGHT => $width - $childSize->width,
        };
        $this->childY = match ($this->alignment) {
            Alignment::TOP_LEFT, Alignment::TOP_CENTER, Alignment::TOP_RIGHT => 0.0,
            Alignment::CENTER_LEFT, Alignment::CENTER, Alignment::CENTER_RIGHT => ($height - $childSize->height) / 2,
            Alignment::BOTTOM_LEFT, Alignment::BOTTOM_CENTER, Alignment::BOTTOM_RIGHT => $height - $childSize->height,
        };

        return $constraints->constrain(new Size($width, $height));
    }

    public function paint(NativeCanvas $canvas, float $x, float $y): void
    {
        $this->child->paint($canvas, $x + $this->childX, $y + $this->childY);
    }
}
