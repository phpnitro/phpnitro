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
 * Phase 3 of docs/proposals/moteur-rendu-natif.md: the layout engine has no
 * live DOM to attach an onclick to, so a tappable region has to be
 * registered explicitly at paint time, in the same absolute-pixel space
 * the draw commands already use. NativeCanvasView.kt hit-tests raw touch
 * coordinates against these rects; a hit fires this node's $action string
 * back to PHP over HTTP, same round-trip shape as nav.js's
 * phpxNav.submitAction() in the HTML pipeline, just without a DOM to
 * re-render into — the whole draw-command list comes back instead.
 */
final class RenderTappable implements RenderNode
{
    private Size $size;

    /**
     * @param ?array<string, mixed> $meta Extra data the client needs to handle this action —
     *                                    e.g. a SelectBox's options, a dialog's message/title.
     *                                    Not needed for plain navigate:/submit:/device: actions.
     *                                    A `'label'` entry is also the escape hatch for
     *                                    NativeCanvasView.kt's accessibility tree
     *                                    (rebuildAccessibilityNodes()): it infers a
     *                                    TalkBack content description from nearby text
     *                                    commands automatically for most widgets, but an
     *                                    icon-only region with no visible text (a raw
     *                                    RenderTappable around an icon, say) has nothing to
     *                                    infer from without one.
     */
    public function __construct(
        private readonly RenderNode $child,
        private readonly string $action,
        private readonly ?array $meta = null,
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
        $canvas->hitRegion($x, $y, $this->size->width, $this->size->height, $this->action, $this->meta);
        $this->child->paint($canvas, $x, $y);
    }
}
