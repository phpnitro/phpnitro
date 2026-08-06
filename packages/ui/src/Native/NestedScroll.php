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
 * A vertically-scrollable region with its OWN bounded viewport height,
 * nested inside the screen's own page scroll — see
 * Canvas::verticalScroll()'s docblock for the exact mechanism and its
 * documented scope boundary (claims the whole gesture on first drag,
 * not full nested-scroll bubble semantics). $viewportHeight is required
 * (not inferred): unlike a normal Column, which naturally hugs its
 * content inside a screen laid out against Constraints::INFINITY (see
 * docs/architecture.md), a nested scroll region needs an explicit cap
 * or there would be nothing to scroll past.
 */
final class NestedScroll implements Widget
{
    private Size $size;
    private float $contentHeight = 0.0;

    public function __construct(
        private readonly string $key,
        private readonly Widget $child,
        private readonly float $viewportHeight,
    ) {
        $this->size = Size::zero();
    }

    public function layout(Constraints $constraints): Size
    {
        // The child measures its own true height against an unbounded
        // max (same reasoning as BottomSheet's own content measurement —
        // see that class's paint() docblock for why a bounded constraint
        // here would make a Flex::column child fill the viewport height
        // instead of reporting how much content there actually is to
        // scroll through).
        $childSize = $this->child->layout(new Constraints(0, $constraints->maxWidth, 0, Constraints::INFINITY));
        $this->contentHeight = $childSize->height;
        $this->size = $constraints->constrain(new Size($constraints->maxWidth, $this->viewportHeight));

        return $this->size;
    }

    public function paint(Canvas $canvas, float $x, float $y): void
    {
        $nested = new Canvas();
        $this->child->paint($nested, 0.0, 0.0);

        $canvas->verticalScroll(
            $this->key,
            $x,
            $y,
            $this->size->width,
            $this->viewportHeight,
            $this->contentHeight,
            $nested->rawCommands(),
            $nested->rawHitRegions(),
        );
    }
}
