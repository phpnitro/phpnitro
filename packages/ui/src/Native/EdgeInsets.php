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

final class EdgeInsets
{
    public function __construct(
        public readonly float $left,
        public readonly float $top,
        public readonly float $right,
        public readonly float $bottom,
    ) {
    }

    public static function all(float $value): self
    {
        return new self($value, $value, $value, $value);
    }

    public static function symmetric(float $horizontal = 0.0, float $vertical = 0.0): self
    {
        return new self($horizontal, $vertical, $horizontal, $vertical);
    }

    public static function only(float $left = 0.0, float $top = 0.0, float $right = 0.0, float $bottom = 0.0): self
    {
        return new self($left, $top, $right, $bottom);
    }

    public function horizontal(): float
    {
        return $this->left + $this->right;
    }

    public function vertical(): float
    {
        return $this->top + $this->bottom;
    }
}
