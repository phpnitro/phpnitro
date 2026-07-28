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
 * v1: children overlaid in paint order (later = on top), all top-left
 * aligned, box sized to the largest child. No Positioned-equivalent yet —
 * add one only once a real widget needs offset overlay children; every
 * current call site works fine with plain stacking.
 */
final class RenderStack implements RenderNode
{
    /**
     * @var array<int, RenderNode>
     */
    private readonly array $children;

    public function __construct(array $children)
    {
        $this->children = $children;
    }

    public function layout(Constraints $constraints): Size
    {
        $width = 0.0;
        $height = 0.0;

        foreach ($this->children as $child) {
            $size = $child->layout($constraints->loosen());
            $width = max($width, $size->width);
            $height = max($height, $size->height);
        }

        return $constraints->constrain(new Size($width, $height));
    }

    public function paint(NativeCanvas $canvas, float $x, float $y): void
    {
        foreach ($this->children as $child) {
            $child->paint($canvas, $x, $y);
        }
    }
}
