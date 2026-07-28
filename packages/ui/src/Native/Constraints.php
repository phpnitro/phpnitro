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
 * Flutter's BoxConstraints, ported as-is: a node never picks its own size in
 * a vacuum, its parent hands it a min/max box and the node must return a
 * Size that fits inside it. This one rule is what makes layout a single
 * top-down pass instead of the HTML pipeline's browser-owned reflow.
 *
 * INFINITY is PHP's real INF, exactly like Flutter's double.infinity.
 * IEEE 754 arithmetic on it is well-behaved for this purpose — INF - 48
 * stays INF, INF / 2 stays INF — the only rule callers must follow is to
 * gate anything that treats a bound as a concrete number behind
 * hasBoundedWidth()/hasBoundedHeight() (is_finite) first. A finite sentinel
 * like 1e7 was tried and rejected: subtracting insets/padding from it
 * during nested layout produces a slightly-smaller-but-still-huge finite
 * number that then wrongly reads as "bounded" a few levels down the tree.
 */
final class Constraints
{
    public const INFINITY = \INF;

    public function __construct(
        public readonly float $minWidth = 0.0,
        public readonly float $maxWidth = self::INFINITY,
        public readonly float $minHeight = 0.0,
        public readonly float $maxHeight = self::INFINITY,
    ) {
    }

    public static function tight(float $width, float $height): self
    {
        return new self($width, $width, $height, $height);
    }

    public static function loose(float $maxWidth, float $maxHeight): self
    {
        return new self(0.0, $maxWidth, 0.0, $maxHeight);
    }

    public function constrainWidth(float $width): float
    {
        return max($this->minWidth, min($this->maxWidth, $width));
    }

    public function constrainHeight(float $height): float
    {
        return max($this->minHeight, min($this->maxHeight, $height));
    }

    public function constrain(Size $size): Size
    {
        return new Size($this->constrainWidth($size->width), $this->constrainHeight($size->height));
    }

    /** Same max bounds, but minimums dropped to 0 — "you may be smaller". */
    public function loosen(): self
    {
        return new self(0.0, $this->maxWidth, 0.0, $this->maxHeight);
    }

    public function tightenWidth(float $width): self
    {
        return new self($width, $width, $this->minHeight, $this->maxHeight);
    }

    public function tightenHeight(float $height): self
    {
        return new self($this->minWidth, $this->maxWidth, $height, $height);
    }

    public function hasBoundedWidth(): bool
    {
        return is_finite($this->maxWidth);
    }

    public function hasBoundedHeight(): bool
    {
        return is_finite($this->maxHeight);
    }
}
