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
 * Children overlaid in paint order (later = on top), box sized to the
 * largest non-positioned child. Plain children stay top-left aligned;
 * RenderPositioned children are offset from whichever edges they specify
 * once the stack's own size is known (needed for e.g. a corner badge).
 */
final class RenderStack implements RenderNode
{
    /**
     * @var array<int, RenderNode>
     */
    private readonly array $children;

    /**
     * @var array<int, Size>
     */
    private array $childSizes = [];

    private float $width = 0.0;
    private float $height = 0.0;

    public function __construct(array $children)
    {
        $this->children = $children;
    }

    public function layout(Constraints $constraints): Size
    {
        $width = 0.0;
        $height = 0.0;
        $this->childSizes = [];

        foreach ($this->children as $child) {
            $size = $child->layout($constraints->loosen());
            $this->childSizes[] = $size;
            if (!$child instanceof RenderPositioned) {
                $width = max($width, $size->width);
                $height = max($height, $size->height);
            }
        }

        $this->width = $width;
        $this->height = $height;

        return $constraints->constrain(new Size($width, $height));
    }

    public function paint(NativeCanvas $canvas, float $x, float $y): void
    {
        foreach ($this->children as $index => $child) {
            if ($child instanceof RenderPositioned) {
                $child->paintIn($canvas, $x, $y, $this->width, $this->height, $this->childSizes[$index]);
                continue;
            }
            $child->paint($canvas, $x, $y);
        }
    }
}
