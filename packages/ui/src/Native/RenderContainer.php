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
 * The native-engine analogue of Container.php's HTML widget: an optionally
 * colored/rounded/bordered box with padding, wrapping a single child. Fixed
 * width/height (if given) win outright — a tight constraint on that axis,
 * same as Flutter's Container. Otherwise the box hugs its (padded) child,
 * clamped to whatever the parent allows.
 */
final class RenderContainer implements RenderNode
{
    private readonly ?RenderNode $content;
    private Size $size;

    public function __construct(
        ?RenderNode $child = null,
        private readonly ?float $width = null,
        private readonly ?float $height = null,
        private readonly ?Color $background = null,
        private readonly float $radius = 0.0,
        private readonly ?Color $borderColor = null,
        private readonly float $borderWidth = 0.0,
        EdgeInsets $padding = new EdgeInsets(0.0, 0.0, 0.0, 0.0),
    ) {
        $this->content = $child !== null ? new RenderPadding($padding, $child) : null;
        $this->size = Size::zero();
    }

    public function layout(Constraints $constraints): Size
    {
        $inner = $constraints;
        if ($this->width !== null) {
            $inner = $inner->tightenWidth($this->width);
        }
        if ($this->height !== null) {
            $inner = $inner->tightenHeight($this->height);
        }

        if ($this->content !== null) {
            $contentSize = $this->content->layout($inner->loosen());
            $width = $this->width ?? $contentSize->width;
            $height = $this->height ?? $contentSize->height;
        } else {
            // No child: an explicit width/height wins outright; otherwise
            // fill whatever box the parent handed us — same as Flutter's
            // Container() with neither a child nor a size, which expands
            // to the incoming constraints rather than collapsing to zero.
            $width = $this->width ?? ($inner->hasBoundedWidth() ? $inner->maxWidth : 0.0);
            $height = $this->height ?? ($inner->hasBoundedHeight() ? $inner->maxHeight : 0.0);
        }

        // The box painted (background/border) is this final, CONSTRAINED
        // size, never the raw content/hug size computed above — a Flexible
        // parent can hand this container a tight allocation even though no
        // explicit width/height was set on it, and the box must fill that
        // allocation exactly (constraints are a contract, not a
        // suggestion), even though its child stays whatever size it
        // naturally measured at.
        $this->size = $constraints->constrain(new Size($width, $height));

        return $this->size;
    }

    public function paint(NativeCanvas $canvas, float $x, float $y): void
    {
        if ($this->background !== null || $this->borderColor !== null) {
            $canvas->rect(
                $x,
                $y,
                $this->size->width,
                $this->size->height,
                $this->background?->toHex(),
                $this->radius,
                $this->borderColor?->toHex(),
                $this->borderWidth,
            );
        }

        $this->content?->paint($canvas, $x, $y);
    }
}
