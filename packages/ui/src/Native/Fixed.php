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
 * Marks its subtree's draw commands/hit regions as screen-relative instead
 * of content-relative — NativeCanvasView.kt draws the scrollable stream
 * translated by -scrollY, then draws everything painted while
 * Canvas::beginFixed()/endFixed() was active a second time with no
 * translate, so it stays pinned while the body scrolls underneath. What
 * Flutter's Scaffold gets from AppBar/BottomNavigationBar living outside
 * the scrollable body — this is the primitive Scaffold builds that
 * on top of, not something call sites should normally reach for directly.
 */
final class Fixed implements Widget
{
    public function __construct(
        private readonly Widget $child,
    ) {
    }

    public function layout(Constraints $constraints): Size
    {
        return $this->child->layout($constraints);
    }

    public function paint(Canvas $canvas, float $x, float $y): void
    {
        $canvas->beginFixed();
        $this->child->paint($canvas, $x, $y);
        $canvas->endFixed();
    }
}
