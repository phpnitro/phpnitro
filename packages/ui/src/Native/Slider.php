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

use Engine\Color;

/**
 * A draggable 0.0-1.0 value picker — the same "NativeCanvasView.kt tracks
 * the whole drag client-side, PHP only sees the final value on release"
 * split as Dismissible/Reorderable/HorizontalScroll (see Canvas::slider()).
 * The commit on release reuses Checkbox/Toggle's existing "toggle:" action
 * (NativeRenderPocActivity's generic handler already does
 * `fieldValues[name] = meta.next; refetch()`) with the dragged value —
 * formatted to 3 decimals — standing in for "next", so no new action
 * dispatch had to be added to the Kotlin side at all; only the drag
 * mechanics (track hit-testing, live thumb follow, gesture-priority
 * disambiguation against vertical scroll) are new.
 */
final class Slider implements Widget
{
    private Size $size;

    public function __construct(
        private readonly string $name,
        private readonly float $value,
        private readonly ?float $width = null,
        private readonly ?Color $trackColor = null,
        private readonly ?Color $activeColor = null,
        private readonly ?Color $thumbColor = null,
        private readonly float $trackHeight = 6.0,
        private readonly float $thumbSize = 22.0,
    ) {
        $this->size = Size::zero();
    }

    public function layout(Constraints $constraints): Size
    {
        $width = $this->width ?? ($constraints->hasBoundedWidth() ? $constraints->maxWidth : 300.0);
        // At least 44dp tall regardless of thumbSize — a slider that's
        // only as tall as a thin track+small thumb would be a real
        // fat-finger miss on a phone; Material's own touch target
        // guidance is exactly this same 44-48dp floor.
        $height = max(44.0, $this->thumbSize);
        $this->size = new Size($width, $height);

        return $constraints->constrain($this->size);
    }

    public function paint(Canvas $canvas, float $x, float $y): void
    {
        $canvas->slider(
            $this->name,
            $x,
            $y,
            $this->size->width,
            $this->size->height,
            $this->trackHeight,
            $this->thumbSize,
            max(0.0, min(1.0, $this->value)),
            ($this->trackColor ?? Tokens::border())->toHex(),
            ($this->activeColor ?? Tokens::ink())->toHex(),
            ($this->thumbColor ?? Color::white())->toHex(),
        );
    }
}
