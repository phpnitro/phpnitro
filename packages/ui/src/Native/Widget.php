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
 * One node in the layout tree. Two passes, same contract Flutter's
 * RenderObject uses: layout() is a top-down negotiation (parent proposes a
 * Constraints box, child returns the Size it settled on — never the other
 * way around, which is what keeps this a single pass instead of a
 * browser-style reflow loop), paint() is a second top-down pass that turns
 * whatever layout() decided into absolute-pixel draw commands.
 */
interface Widget
{
    public function layout(Constraints $constraints): Size;

    public function paint(Canvas $canvas, float $x, float $y): void;
}
