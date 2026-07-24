<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine;

/**
 * CustomPaint equivalent — a plain HTML5 <canvas> (hardware-accelerated,
 * mature in every WebView), with a small fluent PHP builder for the common
 * shapes instead of making every consumer hand-write canvas JS. Not a
 * general-purpose drawing DSL: it maps a short list of ops
 * (rect/circle/line/text) to a JSON array assets/js/canvas.js replays
 * against CanvasRenderingContext2D once, at mount — there's no live
 * update/animation loop here, just a one-shot drawing (see
 * ROADMAP-PARITE-FLUTTER-REACT-NATIVE.md item #5 for what a real
 * CustomPaint-equivalent with per-frame redraw would still need).
 */
final class Canvas extends Widget
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $ops = [];

    public function __construct(
        private readonly int $width = 300,
        private readonly int $height = 150,
        private readonly string $classes = '',
    ) {
    }

    public static function make(int $width = 300, int $height = 150, string $classes = ''): self
    {
        return new self($width, $height, $classes);
    }

    public function rect(int $x, int $y, int $width, int $height, string $color = '#000'): self
    {
        $this->ops[] = ['type' => 'rect', 'x' => $x, 'y' => $y, 'w' => $width, 'h' => $height, 'color' => $color];

        return $this;
    }

    public function circle(int $x, int $y, int $radius, string $color = '#000'): self
    {
        $this->ops[] = ['type' => 'circle', 'x' => $x, 'y' => $y, 'r' => $radius, 'color' => $color];

        return $this;
    }

    public function line(int $x1, int $y1, int $x2, int $y2, string $color = '#000', int $width = 1): self
    {
        $this->ops[] = ['type' => 'line', 'x1' => $x1, 'y1' => $y1, 'x2' => $x2, 'y2' => $y2, 'color' => $color, 'width' => $width];

        return $this;
    }

    public function text(int $x, int $y, string $content, string $color = '#000', string $font = '14px sans-serif'): self
    {
        $this->ops[] = ['type' => 'text', 'x' => $x, 'y' => $y, 'content' => $content, 'color' => $color, 'font' => $font];

        return $this;
    }

    public function render(): string
    {
        return sprintf(
            '<canvas data-phpx-canvas data-ops="%s" width="%d" height="%d" class="%s"></canvas>',
            htmlspecialchars(json_encode($this->ops, JSON_THROW_ON_ERROR), ENT_QUOTES),
            $this->width,
            $this->height,
            htmlspecialchars($this->classes, ENT_QUOTES),
        );
    }
}
