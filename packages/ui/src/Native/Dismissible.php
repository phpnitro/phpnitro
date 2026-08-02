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
 * The native equivalent of Flutter's Dismissible — swipe an item off to
 * either side to remove it. Unlike every other interaction in this
 * pipeline, the drag itself never round-trips to PHP: NativeCanvasView.kt
 * tracks the finger and translates this subtree's own commands live
 * (tagged via NativeCanvas::beginDismiss()/endDismiss()), and only calls
 * back with $action once the swipe commits past threshold on release —
 * PHP sees the outcome ("item 42 dismissed"), never the gesture.
 *
 * $key must be stable and unique among concurrently-visible dismissibles
 * (same requirement as RenderAnimated/RenderHero's $tag) — it's what
 * NativeCanvasView.kt uses to know which commands belong to which
 * swipeable rect.
 */
final class RenderDismissible implements RenderNode
{
    private Size $size;

    public function __construct(
        private readonly RenderNode $child,
        private readonly string $key,
        private readonly string $action,
    ) {
        $this->size = Size::zero();
    }

    public function layout(Constraints $constraints): Size
    {
        $this->size = $this->child->layout($constraints);

        return $this->size;
    }

    public function paint(NativeCanvas $canvas, float $x, float $y): void
    {
        $canvas->dismissible($this->key, $x, $y, $this->size->width, $this->size->height, $this->action);
        $canvas->beginDismiss($this->key);
        $this->child->paint($canvas, $x, $y);
        $canvas->endDismiss();
    }
}
