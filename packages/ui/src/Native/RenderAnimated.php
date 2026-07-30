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
 * The general-purpose implicit-animation wrapper — Flutter's
 * AnimatedContainer/AnimatedOpacity/AnimatedPositioned family unified into
 * one primitive, since they all reduce to the same question: "did the
 * subtree under this $key look different last render? if so, ease into
 * the new state instead of snapping." Same underlying mechanism as
 * RenderHero (NativeCanvas::beginHero()/endHero(), heroRegions, the
 * Matrix-based flight in NativeCanvasView.kt's drawHeroTransition()) — a
 * Hero flight across a navigation and a color/size change on the same
 * screen are the same primitive at the Kotlin level, just used in
 * different contexts. drawHeroTransition() additionally interpolates
 * per-command color/geometry fields (not just the subtree's outer rect),
 * so a background color or radius change eases too, not just position.
 *
 * $key must be stable across renders for the same logical widget (a
 * product ID, a list item's key) and unique among concurrently-animating
 * elements — two different widgets sharing a key would be flown into one
 * another, same footgun Flutter's Hero has with duplicate tags.
 */
final class RenderAnimated implements RenderNode
{
    private Size $size;

    public function __construct(
        private readonly RenderNode $child,
        private readonly string $key,
    ) {
        $this->size = Size::zero();
    }

    public function layout(Constraints $constraints): Size
    {
        $this->size = $this->child->layout($constraints);

        return $this->size;
    }

    public function paint(NativeCanvas $canvas, float $x, float $y): void
    {
        $canvas->beginHero($this->key, $x, $y, $this->size->width, $this->size->height);
        $this->child->paint($canvas, $x, $y);
        $canvas->endHero();
    }
}
