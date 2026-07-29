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
 *
 * toJson()'s shape changed from a flat array to {commands, hitRegions} in
 * phase 3 (hit-testing/actions) — RenderTappable needs somewhere to record
 * "this absolute rect fires this action string" alongside the draw
 * commands, so NativeCanvasView.kt has something to hit-test touches
 * against. Only /native/layout-demo consumes this; Phase 0's /native/demo
 * still uses the frozen flat-array NativeDrawCommand protocol.
 */
final class NativeCanvas
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $commands = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $hitRegions = [];

    private float $contentHeight = 0.0;
    private ?float $renderTimeMs = null;
    private ?string $redirect = null;

    /**
     * The full laid-out content height (which can exceed the viewport) —
     * NativeCanvasView needs this to know how far there is to scroll.
     * Called once with the root RenderNode::layout()'s returned Size.
     */
    public function setContentHeight(float $height): self
    {
        $this->contentHeight = $height;

        return $this;
    }

    /**
     * How long layout()+paint() actually took, in milliseconds — the one
     * real, measured number in the "is this fast?" question instead of an
     * intuition. Excludes HTTP transport and Kotlin-side parse/draw on
     * purpose (see docs/proposals/moteur-rendu-natif.md's definition of
     * done): this isolates the PHP-side cost specifically, since that's
     * the part this architecture is gambling on staying cheap.
     */
    public function setRenderTimeMs(float $ms): self
    {
        $this->renderTimeMs = $ms;

        return $this;
    }

    /**
     * Server-driven navigation — a NativeButton's "submit:" action can
     * change what screen the client should be on (login succeeding, most
     * obviously) the same way LoginPage.php's onLogin() returning a path
     * redirects the HTML pipeline's router. There's no router to
     * re-resolve here, so this just tells NativeRenderPocActivity which
     * screen name to swap the top of its stack to before it re-fetches —
     * see its handling of the "redirect" field.
     */
    public function setRedirect(string $screen): self
    {
        $this->redirect = $screen;

        return $this;
    }

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
        ?string $gradientFrom = null,
        ?string $gradientTo = null,
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
            'gradientFrom' => $gradientFrom,
            'gradientTo' => $gradientTo,
        ], static fn (mixed $value): bool => $value !== null);

        return $this;
    }

    public function text(float $x, float $y, string $text, string $color = '#000000', float $size = 16.0, bool $bold = false, float $letterSpacing = 0.0): self
    {
        $this->commands[] = array_filter([
            'type' => 'text',
            'x' => $x,
            'y' => $y,
            'text' => $text,
            'color' => $color,
            'size' => $size,
            'bold' => $bold ?: null,
            'letterSpacing' => $letterSpacing > 0.0 ? $letterSpacing : null,
        ], static fn (mixed $value): bool => $value !== null);

        return $this;
    }

    /**
     * A Material Icons glyph — NativeCanvasView draws it with
     * Canvas.drawText() against the bundled MaterialIcons-Regular.ttf,
     * exactly the technique Flutter's own Icons class uses internally
     * (an icon is a character, not a bitmap or a hand-drawn path). $x/$y
     * are the icon's top-left corner, same convention as rect()/text();
     * $codepoint comes from MaterialIcons::codepoint($name).
     */
    public function icon(float $x, float $y, float $size, int $codepoint, string $color = '#111827'): self
    {
        $this->commands[] = [
            'type' => 'icon',
            'x' => $x,
            'y' => $y,
            'size' => $size,
            'codepoint' => $codepoint,
            'color' => $color,
        ];

        return $this;
    }

    /**
     * A bitmap loaded asynchronously by NativeCanvasView (HTTP fetch +
     * decode off the main thread, in-memory LRU cache keyed by URL) — the
     * layout engine reserves the box eagerly since it can't know the
     * image's intrinsic size ahead of a network round-trip, same
     * constraint Image.network() has in Flutter. Nothing is drawn for
     * this box until the bitmap finishes loading; the view just
     * invalidates itself when it does.
     */
    public function image(float $x, float $y, float $width, float $height, string $url, float $radius = 0.0): self
    {
        $this->commands[] = array_filter([
            'type' => 'image',
            'x' => $x,
            'y' => $y,
            'width' => $width,
            'height' => $height,
            'url' => $url,
            'radius' => $radius,
        ], static fn (mixed $value): bool => $value !== null);

        return $this;
    }

    /**
     * A raw filled/stroked circle — the primitive RenderContainer's
     * rect()+radius can't express (a rect radius rounds corners, it
     * doesn't produce a circle sized independently of a bounding box), and
     * what Engine\Canvas's ->circle() needs a native equivalent for.
     */
    public function circle(float $cx, float $cy, float $radius, ?string $color = null, ?string $borderColor = null, float $borderWidth = 0.0): self
    {
        $this->commands[] = array_filter([
            'type' => 'circle',
            'cx' => $cx,
            'cy' => $cy,
            'radius' => $radius,
            'color' => $color,
            'borderColor' => $borderColor,
            'borderWidth' => $borderWidth,
        ], static fn (mixed $value): bool => $value !== null);

        return $this;
    }

    /** A raw straight line — what Engine\Canvas's ->line() needs a native equivalent for. */
    public function line(float $x1, float $y1, float $x2, float $y2, string $color, float $width = 1.0): self
    {
        $this->commands[] = [
            'type' => 'line',
            'x1' => $x1,
            'y1' => $y1,
            'x2' => $x2,
            'y2' => $y2,
            'color' => $color,
            'width' => $width,
        ];

        return $this;
    }

    /**
     * @param ?array<string, mixed> $meta Extra data the client needs to handle this action
     *                                    without a second round-trip — a SelectBox's
     *                                    options, a dialog's message/title/confirm action.
     */
    public function hitRegion(float $x, float $y, float $width, float $height, string $action, ?array $meta = null): self
    {
        $this->hitRegions[] = array_filter([
            'x' => $x,
            'y' => $y,
            'width' => $width,
            'height' => $height,
            'action' => $action,
            'meta' => $meta,
        ], static fn (mixed $value): bool => $value !== null);

        return $this;
    }

    public function toJson(): string
    {
        return json_encode(array_filter([
            'commands' => $this->commands,
            'hitRegions' => $this->hitRegions,
            'contentHeight' => $this->contentHeight,
            'renderTimeMs' => $this->renderTimeMs,
            'redirect' => $this->redirect,
        ], static fn (mixed $value): bool => $value !== null), JSON_THROW_ON_ERROR);
    }
}
