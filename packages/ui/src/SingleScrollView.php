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

final class SingleScrollView extends Widget
{
    public function __construct(
        private readonly Widget $child,
        private readonly string $classes = 'overflow-y-auto max-h-screen',
    ) {
    }

    public static function make(Widget $child, string $classes = 'overflow-y-auto max-h-screen'): self
    {
        return new self($child, $classes);
    }

    public function render(): string
    {
        return sprintf(
            '<div class="%s">%s</div>',
            htmlspecialchars($this->classes, ENT_QUOTES),
            $this->child->render(),
        );
    }
}
