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

final class GestureDetector extends Widget
{
    public function __construct(
        private readonly Widget $child,
        private readonly ?string $onDoubleClick = null,
        private readonly ?string $onSwipeLeft = null,
        private readonly ?string $onSwipeRight = null,
        private readonly string $classes = '',
    ) {
    }

    public static function make(
        Widget $child,
        ?string $onDoubleClick = null,
        ?string $onSwipeLeft = null,
        ?string $onSwipeRight = null,
        string $classes = '',
    ): self {
        return new self($child, $onDoubleClick, $onSwipeLeft, $onSwipeRight, $classes);
    }

    public function render(): string
    {
        $attrs = [];

        if ($this->onDoubleClick !== null) {
            $attrs[] = 'data-on-dblclick="' . htmlspecialchars($this->onDoubleClick, ENT_QUOTES) . '"';
        }

        if ($this->onSwipeLeft !== null) {
            $attrs[] = 'data-on-swipe-left="' . htmlspecialchars($this->onSwipeLeft, ENT_QUOTES) . '"';
        }

        if ($this->onSwipeRight !== null) {
            $attrs[] = 'data-on-swipe-right="' . htmlspecialchars($this->onSwipeRight, ENT_QUOTES) . '"';
        }

        return sprintf(
            '<div class="gesture-area %s" %s>%s</div>',
            htmlspecialchars($this->classes, ENT_QUOTES),
            implode(' ', $attrs),
            $this->child->render(),
        );
    }
}
