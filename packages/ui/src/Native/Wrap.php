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
 * Children flow left-to-right, wrapping to a new line once a child would
 * overflow the available width — the native-tree equivalent of
 * Engine\Wrap (a flex-wrap Tailwind class), needed because a Canvas has no
 * flexbox to fall back on.
 */
final class Wrap implements Widget
{
    /**
     * @var array<int, Widget>
     */
    private readonly array $children;

    /**
     * @var array<int, array{node: Widget, x: float, y: float}>
     */
    private array $positioned = [];

    private float $width = 0.0;
    private float $height = 0.0;

    public function __construct(
        array $children,
        private readonly float $spacing = 8.0,
        private readonly float $runSpacing = 8.0,
    ) {
        $this->children = $children;
    }

    public function layout(Constraints $constraints): Size
    {
        $maxWidth = $constraints->hasBoundedWidth() ? $constraints->maxWidth : Constraints::INFINITY;

        $this->positioned = [];
        $cursorX = 0.0;
        $cursorY = 0.0;
        $rowHeight = 0.0;
        $contentWidth = 0.0;

        foreach ($this->children as $child) {
            $size = $child->layout($constraints->loosen());

            if ($cursorX > 0.0 && $cursorX + $size->width > $maxWidth) {
                $cursorX = 0.0;
                $cursorY += $rowHeight + $this->runSpacing;
                $rowHeight = 0.0;
            }

            $this->positioned[] = ['node' => $child, 'x' => $cursorX, 'y' => $cursorY];
            $contentWidth = max($contentWidth, $cursorX + $size->width);
            $rowHeight = max($rowHeight, $size->height);
            $cursorX += $size->width + $this->spacing;
        }

        $this->width = $constraints->hasBoundedWidth() ? $maxWidth : $contentWidth;
        $this->height = $cursorY + $rowHeight;

        return $constraints->constrain(new Size($this->width, $this->height));
    }

    public function paint(Canvas $canvas, float $x, float $y): void
    {
        foreach ($this->positioned as $entry) {
            $entry['node']->paint($canvas, $x + $entry['x'], $y + $entry['y']);
        }
    }
}
