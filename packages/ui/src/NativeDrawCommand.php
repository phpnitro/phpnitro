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
 * Phase 0/1 of docs/proposals/moteur-rendu-natif.md — a flat list of
 * primitive draw operations in absolute pixel coordinates, serialized to
 * JSON and replayed by NativeCanvasView.kt against a real Android Canvas
 * (Skia at the OS level, no WebView). No layout engine yet (phase 2): every
 * position here is explicit, not computed from a widget tree.
 *
 * Deliberately NOT part of the Widget/render() HTML pipeline — this is a
 * parallel, experimental rendering path, not a replacement for it yet (see
 * the proposal doc's phased plan and "ce qui ne change pas" section).
 */
final class NativeDrawCommand
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $commands = [];

    public static function make(): self
    {
        return new self();
    }

    public function rect(float $x, float $y, float $width, float $height, string $color, float $radius = 0.0): self
    {
        $this->commands[] = [
            'type' => 'rect',
            'x' => $x,
            'y' => $y,
            'width' => $width,
            'height' => $height,
            'color' => $color,
            'radius' => $radius,
        ];

        return $this;
    }

    public function text(float $x, float $y, string $text, string $color = '#000000', float $size = 16.0): self
    {
        $this->commands[] = [
            'type' => 'text',
            'x' => $x,
            'y' => $y,
            'text' => $text,
            'color' => $color,
            'size' => $size,
        ];

        return $this;
    }

    public function toJson(): string
    {
        return json_encode($this->commands, JSON_THROW_ON_ERROR);
    }
}
