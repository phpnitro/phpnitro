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

final class RenderPadding implements RenderNode
{
    public function __construct(
        private readonly EdgeInsets $insets,
        private readonly RenderNode $child,
    ) {
    }

    public function layout(Constraints $constraints): Size
    {
        $innerConstraints = new Constraints(
            max(0.0, $constraints->minWidth - $this->insets->horizontal()),
            max(0.0, $constraints->maxWidth - $this->insets->horizontal()),
            max(0.0, $constraints->minHeight - $this->insets->vertical()),
            max(0.0, $constraints->maxHeight - $this->insets->vertical()),
        );

        $childSize = $this->child->layout($innerConstraints);

        return $constraints->constrain(new Size(
            $childSize->width + $this->insets->horizontal(),
            $childSize->height + $this->insets->vertical(),
        ));
    }

    public function paint(NativeCanvas $canvas, float $x, float $y): void
    {
        $this->child->paint($canvas, $x + $this->insets->left, $y + $this->insets->top);
    }
}
