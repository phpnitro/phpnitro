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
 * A scrollable row nested inside the screen's own vertical scroll — the
 * "carousel inside a list" case `LazyList` alone can't cover, since it only
 * virtualizes ONE full-screen scroll axis per screen. This is the opposite
 * trade-off: no virtualization at all (every child is laid out and painted
 * up front, so it's for a bounded number of children — a category rail, a
 * card carousel — not a long list), in exchange for the drag itself being
 * 100% client-side, the same "no PHP round-trip mid-gesture" pattern
 * `Dismissible`/`Reorderable` already use. NativeCanvasView.kt keeps a
 * local `key -> horizontal offset` map (seeded to 0, clamped to
 * [0, contentWidth - viewportWidth]) and disambiguates the drag direction
 * against the outer vertical scroll the same way it already does for
 * Dismissible's horizontal swipe.
 *
 * Nesting a second HorizontalScroll (or any independent vertical scroll)
 * inside this one is NOT supported — the client-side gesture/paint code
 * only tracks one level of nested scroll region per touch.
 */
final class HorizontalScroll implements Widget
{
    private Size $size;

    /** @var Widget[] */
    private readonly array $children;

    private float $contentWidth = 0.0;

    /** @var array<int, float> */
    private array $childOffsets = [];

    /**
     * @param Widget[] $children
     */
    public function __construct(
        private readonly string $key,
        array $children,
        private readonly float $gap = 0.0,
    ) {
        $this->children = array_values($children);
    }

    public function layout(Constraints $constraints): Size
    {
        $maxHeight = 0.0;
        $x = 0.0;

        foreach ($this->children as $child) {
            // Children get an effectively unbounded width to report their
            // own intrinsic size — the row's total width is whatever they
            // add up to, not clipped to the viewport constraints gives us.
            $childSize = $child->layout(new Constraints(0, Constraints::INFINITY, 0, $constraints->maxHeight));
            $this->childOffsets[] = $x;
            $x += $childSize->width + $this->gap;
            $maxHeight = max($maxHeight, $childSize->height);
        }

        $this->contentWidth = $this->children === [] ? 0.0 : $x - $this->gap;
        $this->size = new Size($constraints->maxWidth, $maxHeight);

        return $this->size;
    }

    public function paint(Canvas $canvas, float $x, float $y): void
    {
        $nested = new Canvas();
        foreach ($this->children as $index => $child) {
            $child->paint($nested, $this->childOffsets[$index], 0.0);
        }

        $canvas->horizontalScroll(
            $this->key,
            $x,
            $y,
            $this->size->width,
            $this->size->height,
            $this->contentWidth,
            $nested->rawCommands(),
            $nested->rawHitRegions(),
        );
    }
}
