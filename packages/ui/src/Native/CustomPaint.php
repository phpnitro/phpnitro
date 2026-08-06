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
 * The native-tree equivalent of Engine\Canvas — a fixed-size box you paint
 * into with absolute (box-relative) coordinates, replayed as flat
 * Canvas commands offset by wherever layout placed this box. Single
 * draw at paint time, same "no diffing, one-shot" contract Engine\Canvas
 * has (it draws once at mount, not on every state change either).
 */
final class CustomPaint implements Widget
{
    /**
     * @var array<int, array{op: string, args: array<string, mixed>}>
     */
    private array $ops = [];

    private function __construct(
        private readonly float $width,
        private readonly float $height,
    ) {
    }

    public static function make(float $width, float $height): self
    {
        return new self($width, $height);
    }

    public function rect(float $x, float $y, float $width, float $height, string $color, float $radius = 0.0): self
    {
        $this->ops[] = ['op' => 'rect', 'args' => compact('x', 'y', 'width', 'height', 'color', 'radius')];

        return $this;
    }

    public function circle(float $cx, float $cy, float $radius, string $color): self
    {
        $this->ops[] = ['op' => 'circle', 'args' => compact('cx', 'cy', 'radius', 'color')];

        return $this;
    }

    public function line(float $x1, float $y1, float $x2, float $y2, string $color, float $width = 1.0): self
    {
        $this->ops[] = ['op' => 'line', 'args' => compact('x1', 'y1', 'x2', 'y2', 'color', 'width')];

        return $this;
    }

    public function text(float $x, float $y, string $text, string $color = '#000000', float $size = 16.0): self
    {
        $this->ops[] = ['op' => 'text', 'args' => compact('x', 'y', 'text', 'color', 'size')];

        return $this;
    }

    public function layout(Constraints $constraints): Size
    {
        return $constraints->constrain(new Size($this->width, $this->height));
    }

    public function paint(Canvas $canvas, float $x, float $y): void
    {
        foreach ($this->ops as $entry) {
            $args = $entry['args'];
            match ($entry['op']) {
                'rect' => $canvas->rect($x + $args['x'], $y + $args['y'], $args['width'], $args['height'], $args['color'], $args['radius']),
                'circle' => $canvas->circle($x + $args['cx'], $y + $args['cy'], $args['radius'], $args['color']),
                'line' => $canvas->line($x + $args['x1'], $y + $args['y1'], $x + $args['x2'], $y + $args['y2'], $args['color'], $args['width']),
                'text' => $canvas->text($x + $args['x'], $y + $args['y'], $args['text'], $args['color'], $args['size']),
            };
        }
    }
}
