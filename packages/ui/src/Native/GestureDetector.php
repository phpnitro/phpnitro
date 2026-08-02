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
 * The native-tree equivalent of Engine\GestureDetector — a real
 * android.view.GestureDetector wired into NativeCanvasView.kt's
 * onTouchEvent (onDoubleTap()/onFling()), not a JS dblclick/touch-delta
 * listener. The three actions travel in the hit region's meta under
 * fixed keys ("onDoubleClick"/"onSwipeLeft"/"onSwipeRight") that
 * NativeCanvasView.kt's dispatchGestureAction() looks for specifically —
 * a plain single tap inside the region does nothing, same as the HTML
 * widget's bare `<div class="gesture-area">` with no onclick.
 *
 * onPinch/onRotate are NOT ported — real multi-touch tracking (two
 * pointers, live scale/rotation delta), a meaningfully different and
 * bigger piece of work than single-pointer double-tap/fling detection.
 */
final class NativeGestureDetector implements RenderNode
{
    private readonly RenderNode $content;

    public function __construct(
        RenderNode $child,
        ?string $onDoubleClick = null,
        ?string $onSwipeLeft = null,
        ?string $onSwipeRight = null,
    ) {
        $meta = array_filter([
            'onDoubleClick' => $onDoubleClick,
            'onSwipeLeft' => $onSwipeLeft,
            'onSwipeRight' => $onSwipeRight,
        ], static fn (?string $value): bool => $value !== null);

        $this->content = new RenderTappable($child, 'noop', $meta);
    }

    public function layout(Constraints $constraints): Size
    {
        return $this->content->layout($constraints);
    }

    public function paint(NativeCanvas $canvas, float $x, float $y): void
    {
        $this->content->paint($canvas, $x, $y);
    }
}
