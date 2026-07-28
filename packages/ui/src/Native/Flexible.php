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
 * Wraps a child with a flex factor for use inside RenderFlex, mirroring
 * Flutter's Expanded/Flexible — a plain child (not wrapped in this) keeps
 * its intrinsic size and flex 0, exactly like an un-Expanded child in a
 * Flutter Row/Column.
 */
final class Flexible implements RenderNode
{
    public function __construct(
        public readonly RenderNode $child,
        public readonly int $flex = 1,
    ) {
    }

    public function layout(Constraints $constraints): Size
    {
        return $this->child->layout($constraints);
    }

    public function paint(NativeCanvas $canvas, float $x, float $y): void
    {
        $this->child->paint($canvas, $x, $y);
    }
}
