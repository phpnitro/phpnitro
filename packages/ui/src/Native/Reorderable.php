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
 * The native equivalent of Flutter's ReorderableListView — a vertical
 * stack of items the user can long-press and drag to reorder. Same split
 * as RenderDismissible: NativeCanvasView.kt tracks the whole drag
 * (long-press detection, live follow, slot-swapping as the dragged item
 * crosses a neighbor, settle animation) entirely client-side, and only
 * calls back with $action once the finger lifts — carrying the final key
 * order as a comma-separated suffix (`"{$action}:key3,key1,key2"`), not a
 * per-frame round-trip.
 *
 * $items is ordered (key => child) — that order is what PHP authored this
 * render with; NativeCanvasView.kt only ever reorders relative to it
 * during a live drag, never invents an ordering PHP didn't already
 * express. Every child gets the SAME width (this container's own), only
 * heights can vary — reordering a horizontally-arranged group isn't
 * supported.
 */
final class RenderReorderable implements RenderNode
{
    private Size $size;

    /** @var array<string, Size> */
    private array $itemSizes = [];

    /**
     * @param array<string, RenderNode> $items key => child, in initial order
     */
    public function __construct(
        private readonly string $group,
        private readonly array $items,
        private readonly string $action,
    ) {
        $this->size = Size::zero();
    }

    public function layout(Constraints $constraints): Size
    {
        $width = 0.0;
        $height = 0.0;
        $this->itemSizes = [];

        foreach ($this->items as $key => $item) {
            $itemSize = $item->layout($constraints->loosen());
            $this->itemSizes[$key] = $itemSize;
            $width = max($width, $itemSize->width);
            $height += $itemSize->height;
        }

        $this->size = new Size($width, $height);

        return $constraints->constrain($this->size);
    }

    public function paint(NativeCanvas $canvas, float $x, float $y): void
    {
        $cursorY = $y;
        foreach ($this->items as $key => $item) {
            $itemSize = $this->itemSizes[$key];
            $canvas->reorderItem($this->group, $key, $x, $cursorY, $itemSize->width, $itemSize->height, $this->action);
            $canvas->beginReorder($key);
            $item->paint($canvas, $x, $cursorY);
            $canvas->endReorder();
            $cursorY += $itemSize->height;
        }
    }
}
