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
 * Only meaningful as a direct child of RenderStack — paintIn() is called
 * by RenderStack once the stack's own box size is known, offsetting this
 * child from whichever edges were given (unset edges default to 0, same
 * as Engine\Positioned's HTML equivalent).
 */
final class RenderPositioned implements RenderNode
{
    public function __construct(
        private readonly RenderNode $child,
        private readonly ?float $top = null,
        private readonly ?float $right = null,
        private readonly ?float $bottom = null,
        private readonly ?float $left = null,
    ) {
    }

    public function layout(Constraints $constraints): Size
    {
        return $this->child->layout($constraints->loosen());
    }

    public function paint(NativeCanvas $canvas, float $x, float $y): void
    {
        $this->child->paint($canvas, $x, $y);
    }

    public function paintIn(NativeCanvas $canvas, float $stackX, float $stackY, float $stackWidth, float $stackHeight, Size $childSize): void
    {
        $x = match (true) {
            $this->left !== null => $this->left,
            $this->right !== null => $stackWidth - $childSize->width - $this->right,
            default => 0.0,
        };
        $y = match (true) {
            $this->top !== null => $this->top,
            $this->bottom !== null => $stackHeight - $childSize->height - $this->bottom,
            default => 0.0,
        };

        $this->child->paint($canvas, $stackX + $x, $stackY + $y);
    }
}
