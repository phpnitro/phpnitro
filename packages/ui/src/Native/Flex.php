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
 * Flutter's Flex algorithm (Row/Column are both this with a fixed Axis):
 * two-pass sizing along the main axis — inflexible children first (they get
 * as much main-axis space as they want), then whatever's left over is
 * divided among Flexible children by flex factor. Cross axis is a single
 * pass: STRETCH gives every child a tight constraint at the container's
 * cross size, everything else gives a loose one and aligns after the fact.
 *
 * A plain (non-Flexible) child behaves like an un-Expanded child in a real
 * Flutter Row/Column: it keeps its intrinsic main-axis size.
 */
final class Flex implements Widget
{
    /**
     * @var array<int, Widget>
     */
    private readonly array $children;

    /**
     * @var array<int, Size>
     */
    private array $childSizes = [];

    /**
     * @var array<int, array{0: float, 1: float}>
     */
    private array $childOffsets = [];

    /**
     * @param array<int, Widget> $children Wrap a child in Flexible to let it grow into leftover main-axis space.
     */
    public function __construct(
        private readonly Axis $direction,
        array $children,
        private readonly MainAxisAlignment $mainAxisAlignment = MainAxisAlignment::START,
        private readonly CrossAxisAlignment $crossAxisAlignment = CrossAxisAlignment::START,
    ) {
        $this->children = $children;
    }

    public static function row(array $children, MainAxisAlignment $mainAxisAlignment = MainAxisAlignment::START, CrossAxisAlignment $crossAxisAlignment = CrossAxisAlignment::START): self
    {
        return new self(Axis::HORIZONTAL, $children, $mainAxisAlignment, $crossAxisAlignment);
    }

    public static function column(array $children, MainAxisAlignment $mainAxisAlignment = MainAxisAlignment::START, CrossAxisAlignment $crossAxisAlignment = CrossAxisAlignment::START): self
    {
        return new self(Axis::VERTICAL, $children, $mainAxisAlignment, $crossAxisAlignment);
    }

    public function layout(Constraints $constraints): Size
    {
        $horizontal = $this->direction === Axis::HORIZONTAL;
        $boundedMain = $horizontal ? $constraints->hasBoundedWidth() : $constraints->hasBoundedHeight();
        $mainMax = $horizontal ? $constraints->maxWidth : $constraints->maxHeight;
        $boundedCross = $horizontal ? $constraints->hasBoundedHeight() : $constraints->hasBoundedWidth();
        $crossMax = $horizontal ? $constraints->maxHeight : $constraints->maxWidth;
        $stretch = $this->crossAxisAlignment === CrossAxisAlignment::STRETCH && $boundedCross;

        $this->childSizes = [];
        $usedMain = 0.0;
        $totalFlex = 0;

        foreach ($this->children as $index => $child) {
            if ($child instanceof Flexible) {
                $totalFlex += $child->flex;
                continue;
            }
            $size = $child->layout($this->axisConstraints($horizontal, 0.0, Constraints::INFINITY, $stretch, $crossMax));
            $this->childSizes[$index] = $size;
            $usedMain += $horizontal ? $size->width : $size->height;
        }

        $remaining = $boundedMain ? max(0.0, $mainMax - $usedMain) : 0.0;

        foreach ($this->children as $index => $child) {
            if (!($child instanceof Flexible)) {
                continue;
            }
            $allocated = $totalFlex > 0 ? $remaining * ($child->flex / $totalFlex) : 0.0;
            $size = $child->layout($this->axisConstraints($horizontal, $allocated, $allocated, $stretch, $crossMax));
            $this->childSizes[$index] = $size;
            $usedMain += $horizontal ? $size->width : $size->height;
        }

        $crossSize = $stretch ? $crossMax : 0.0;
        if (!$stretch) {
            foreach ($this->childSizes as $size) {
                $crossSize = max($crossSize, $horizontal ? $size->height : $size->width);
            }
        }

        $mainSize = $boundedMain ? $mainMax : $usedMain;

        $this->positionChildren($horizontal, $mainSize, $crossSize, $usedMain, $totalFlex);

        return $constraints->constrain($horizontal ? new Size($mainSize, $crossSize) : new Size($crossSize, $mainSize));
    }

    private function axisConstraints(bool $horizontal, float $mainMin, float $mainMax, bool $stretch, float $crossMax): Constraints
    {
        $crossMin = $stretch ? $crossMax : 0.0;

        return $horizontal
            ? new Constraints($mainMin, $mainMax, $crossMin, $crossMax)
            : new Constraints($crossMin, $crossMax, $mainMin, $mainMax);
    }

    private function positionChildren(bool $horizontal, float $mainSize, float $crossSize, float $usedMain, int $totalFlex): void
    {
        $slack = max(0.0, $mainSize - $usedMain);
        $count = count($this->children);

        [$leadingSpace, $betweenSpace] = match (true) {
            $totalFlex > 0 => [0.0, 0.0],
            $this->mainAxisAlignment === MainAxisAlignment::CENTER => [$slack / 2, 0.0],
            $this->mainAxisAlignment === MainAxisAlignment::END => [$slack, 0.0],
            $this->mainAxisAlignment === MainAxisAlignment::SPACE_BETWEEN => [0.0, $count > 1 ? $slack / ($count - 1) : 0.0],
            $this->mainAxisAlignment === MainAxisAlignment::SPACE_AROUND => [$count > 0 ? $slack / $count / 2 : 0.0, $count > 0 ? $slack / $count : 0.0],
            $this->mainAxisAlignment === MainAxisAlignment::SPACE_EVENLY => [$slack / ($count + 1), $slack / ($count + 1)],
            default => [0.0, 0.0],
        };

        $mainOffset = $leadingSpace;

        foreach ($this->children as $index => $child) {
            $size = $this->childSizes[$index];
            $mainChildSize = $horizontal ? $size->width : $size->height;
            $crossChildSize = $horizontal ? $size->height : $size->width;

            $crossOffset = match ($this->crossAxisAlignment) {
                CrossAxisAlignment::CENTER => ($crossSize - $crossChildSize) / 2,
                CrossAxisAlignment::END => $crossSize - $crossChildSize,
                default => 0.0,
            };

            $this->childOffsets[$index] = $horizontal ? [$mainOffset, $crossOffset] : [$crossOffset, $mainOffset];

            $mainOffset += $mainChildSize + $betweenSpace;
        }
    }

    public function paint(Canvas $canvas, float $x, float $y): void
    {
        foreach ($this->children as $index => $child) {
            [$dx, $dy] = $this->childOffsets[$index];
            $child->paint($canvas, $x + $dx, $y + $dy);
        }
    }
}
