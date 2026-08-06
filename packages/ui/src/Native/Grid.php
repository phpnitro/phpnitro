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
 * Flutter's GridView.builder, same windowed-prefetch idea as LazyList —
 * see that class's own docblock for why (a request/response pipeline has
 * no persistent connection to lazily build items over as the user's
 * finger crosses into view, so this builds only the ROWS within
 * [scrollY - buffer, scrollY + viewportHeight + buffer] instead of all
 * $itemCount items, and still reports the FULL virtual height as this
 * node's Size so the client's scroll range covers the whole grid).
 *
 * Fixed $columns and $itemHeight (no intrinsic-height items, no
 * responsive column count) — same tradeoff LazyList makes for the same
 * reason: knowing an item's absolute row/column position without laying
 * out every item first requires a fixed cell size. Each cell's width is
 * $constraints->maxWidth / $columns, minus $spacing between columns.
 */
final class Grid implements Widget
{
    private Size $size;

    /** @var array<int, Widget> */
    private array $builtItems = [];

    private float $itemWidth = 0.0;

    /**
     * @param callable(int): Widget $itemBuilder
     */
    public function __construct(
        private readonly int $itemCount,
        private readonly \Closure $itemBuilder,
        private readonly int $columns,
        private readonly float $itemHeight,
        private readonly float $scrollY,
        private readonly float $viewportHeight,
        private readonly float $spacing = 0.0,
        private readonly float $bufferViewports = 2.0,
    ) {
        $this->size = Size::zero();
    }

    public function layout(Constraints $constraints): Size
    {
        $columns = max(1, $this->columns);
        $this->itemWidth = ($constraints->maxWidth - $this->spacing * ($columns - 1)) / $columns;
        $rowCount = (int) ceil($this->itemCount / $columns);
        $rowHeight = $this->itemHeight + $this->spacing;

        $buffer = $this->viewportHeight * $this->bufferViewports;
        $startRow = max(0, (int) floor(($this->scrollY - $buffer) / $rowHeight));
        $endRow = min($rowCount - 1, (int) ceil(($this->scrollY + $this->viewportHeight + $buffer) / $rowHeight));

        $this->builtItems = [];
        $itemConstraints = Constraints::tight($this->itemWidth, $this->itemHeight);
        for ($row = $startRow; $row <= $endRow; $row++) {
            for ($col = 0; $col < $columns; $col++) {
                $index = $row * $columns + $col;
                if ($index >= $this->itemCount) {
                    break;
                }
                $item = ($this->itemBuilder)($index);
                $item->layout($itemConstraints);
                $this->builtItems[$index] = $item;
            }
        }

        $totalHeight = $rowCount > 0 ? $rowCount * $rowHeight - $this->spacing : 0.0;
        $this->size = new Size($constraints->maxWidth, max(0.0, $totalHeight));

        return $this->size;
    }

    public function paint(Canvas $canvas, float $x, float $y): void
    {
        $columns = max(1, $this->columns);
        $rowHeight = $this->itemHeight + $this->spacing;
        $colWidth = $this->itemWidth + $this->spacing;

        foreach ($this->builtItems as $index => $item) {
            $row = intdiv($index, $columns);
            $col = $index % $columns;
            $item->paint($canvas, $x + $col * $colWidth, $y + $row * $rowHeight);
        }
    }
}
