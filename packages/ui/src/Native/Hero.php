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
 * The native equivalent of Engine\Hero — same "$tag" idea, but there's no
 * DOM/CSS transition to hand this off to. The child's own bounding box
 * (this node's layout() Size, at the x/y paint() is called with) is
 * recorded as a heroRegion under $tag; if the SAME tag appears in the next
 * render at a different rect, NativeCanvasView.kt flies the tagged
 * subtree there instead of just crossfading in place (see
 * Canvas::beginHero()/endHero() and drawHeroTransition()).
 */
final class Hero implements Widget
{
    private Size $size;

    public function __construct(
        private readonly Widget $child,
        private readonly string $tag,
        private readonly ?Curve $curve = null,
    ) {
        $this->size = Size::zero();
    }

    public function layout(Constraints $constraints): Size
    {
        $this->size = $this->child->layout($constraints);

        return $this->size;
    }

    public function paint(Canvas $canvas, float $x, float $y): void
    {
        $canvas->beginHero($this->tag, $x, $y, $this->size->width, $this->size->height, $this->curve);
        $this->child->paint($canvas, $x, $y);
        $canvas->endHero();
    }
}
