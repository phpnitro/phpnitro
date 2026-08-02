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
 * The framework's first genuine client-side state primitive — everything
 * else in the render pipeline treats every interaction as "refetch the
 * whole screen from PHP" (see NativeRenderPocActivity.kt's refetch()),
 * which is correct for anything that touches real app/business state, but
 * wrong for a tab switch: nothing about which of these panels is visible
 * needs PHP at all, since every panel's content already travelled to the
 * device in this same response. Switching tabs should feel instant and
 * work offline, the same way Flutter's TabBarView holds its selected index
 * in local State rather than round-tripping to a backend.
 *
 * All panels are laid out (RenderClientTabs's own Size is the max of every
 * panel's, so the container doesn't resize when the selection changes —
 * changing height locally, with no server involved to re-layout whatever
 * comes after it on the screen, isn't something this primitive can support)
 * and painted into their own nested NativeCanvas at paint() time.
 * NativeCanvasView.kt keeps a local `key -> selected index` map (seeded
 * once from $initialIndex, never overwritten by a later render of the same
 * key) and draws/hit-tests only the selected panel — tapping a header (a
 * normal RenderTappable with action "clientTab:{$key}:{$index}") flips
 * that local map with zero network call.
 */
final class RenderClientTabs implements RenderNode
{
    private Size $size;

    /** @var RenderNode[] */
    private readonly array $panels;

    /**
     * @param RenderNode[] $panels
     */
    public function __construct(
        private readonly string $key,
        array $panels,
        private readonly int $initialIndex = 0,
    ) {
        $this->panels = array_values($panels);
        $this->size = Size::zero();
    }

    public function layout(Constraints $constraints): Size
    {
        $maxWidth = 0.0;
        $maxHeight = 0.0;
        foreach ($this->panels as $panel) {
            $panelSize = $panel->layout($constraints);
            $maxWidth = max($maxWidth, $panelSize->width);
            $maxHeight = max($maxHeight, $panelSize->height);
        }
        $this->size = $constraints->constrain(new Size($maxWidth, $maxHeight));

        return $this->size;
    }

    public function paint(NativeCanvas $canvas, float $x, float $y): void
    {
        foreach ($this->panels as $index => $panel) {
            $nested = new NativeCanvas();
            $panel->paint($nested, 0.0, 0.0);
            $canvas->clientTabPanel($this->key, $index, $index === $this->initialIndex, $x, $y, $nested->rawCommands(), $nested->rawHitRegions());
        }
    }
}
