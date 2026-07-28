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
 * The layout engine's paint target: RenderNode::paint() calls append flat
 * draw commands here in absolute pixel coordinates (layout has already
 * resolved every position by the time paint() runs), then toJson() hands
 * the array to NativeCanvasView.kt for replay against a real Canvas.
 *
 * Superset of the Phase 0 NativeDrawCommand protocol (rect/text) — adds
 * optional border fields on rect and lets text carry an explicit baseline
 * so RenderText's line-wrapping can emit one command per line. Kept as a
 * separate class rather than extending NativeDrawCommand because Phase 0's
 * demo route is intentionally frozen (docs/proposals/moteur-rendu-natif.md)
 * and shouldn't shift under a change meant for the layout engine.
 */
final class NativeCanvas
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $commands = [];

    public function rect(
        float $x,
        float $y,
        float $width,
        float $height,
        ?string $color = null,
        float $radius = 0.0,
        ?string $borderColor = null,
        float $borderWidth = 0.0,
        float $elevation = 0.0,
    ): self {
        $this->commands[] = array_filter([
            'type' => 'rect',
            'x' => $x,
            'y' => $y,
            'width' => $width,
            'height' => $height,
            'color' => $color,
            'radius' => $radius,
            'borderColor' => $borderColor,
            'borderWidth' => $borderWidth,
            'elevation' => $elevation > 0.0 ? $elevation : null,
        ], static fn (mixed $value): bool => $value !== null);

        return $this;
    }

    public function text(float $x, float $y, string $text, string $color = '#000000', float $size = 16.0, bool $bold = false): self
    {
        $this->commands[] = array_filter([
            'type' => 'text',
            'x' => $x,
            'y' => $y,
            'text' => $text,
            'color' => $color,
            'size' => $size,
            'bold' => $bold ?: null,
        ], static fn (mixed $value): bool => $value !== null);

        return $this;
    }

    public function toJson(): string
    {
        return json_encode($this->commands, JSON_THROW_ON_ERROR);
    }
}
