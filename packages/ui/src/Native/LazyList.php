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
 * Flutter's ListView.builder, adapted to a request/response pipeline that
 * has no persistent connection to lazily build items over: PHP can't push
 * a new item as the user's finger crosses into view mid-scroll, so instead
 * of building all $itemCount items every request, this builds only the
 * ones within [scrollY - buffer, scrollY + viewportHeight + buffer] —
 * a windowed prefetch, not true per-frame laziness — and reports the FULL
 * virtual height ($itemCount * $itemHeight) as this node's Size so
 * NativeCanvasView.kt's scrollbar/scroll range covers the whole list even
 * though most items were never built or painted.
 *
 * Requires a fixed $itemHeight (no intrinsic-height items) — the only way
 * to know the total content height, and any item's absolute Y position,
 * without laying out every item just to measure it. A future variable-
 * height version would need either a client-reported per-item height
 * cache or an estimate-then-correct scheme; out of scope here.
 *
 * $scrollY/$viewportHeight are Scaffold/Canvas::setScrollFollow()'s
 * responsibility to wire up — see NativeListScreen-style call sites: read
 * $_GET['scroll_y'] (NativeRenderPocActivity reports it on every fetch),
 * pass it here, and call $canvas->setScrollFollow() so the client
 * re-fetches as the user scrolls near the edge of the loaded window
 * instead of only building the first screenful once.
 */
final class LazyList implements Widget
{
    private Size $size;

    /** @var array<int, Widget> */
    private array $builtItems = [];

    /**
     * @param callable(int): Widget $itemBuilder
     */
    public function __construct(
        private readonly int $itemCount,
        private readonly \Closure $itemBuilder,
        private readonly float $itemHeight,
        private readonly float $scrollY,
        private readonly float $viewportHeight,
        private readonly float $bufferViewports = 2.0,
    ) {
        $this->size = Size::zero();
    }

    public function layout(Constraints $constraints): Size
    {
        $buffer = $this->viewportHeight * $this->bufferViewports;
        $startIndex = max(0, (int) floor(($this->scrollY - $buffer) / $this->itemHeight));
        $endIndex = min($this->itemCount - 1, (int) ceil(($this->scrollY + $this->viewportHeight + $buffer) / $this->itemHeight));

        $this->builtItems = [];
        for ($index = $startIndex; $index <= $endIndex; $index++) {
            $item = ($this->itemBuilder)($index);
            $item->layout($constraints->tightenHeight($this->itemHeight));
            $this->builtItems[$index] = $item;
        }

        $this->size = new Size($constraints->maxWidth, $this->itemCount * $this->itemHeight);

        return $this->size;
    }

    public function paint(Canvas $canvas, float $x, float $y): void
    {
        foreach ($this->builtItems as $index => $item) {
            $item->paint($canvas, $x, $y + $index * $this->itemHeight);
        }
    }
}
