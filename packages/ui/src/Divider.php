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

final class Divider extends Widget
{
    public function __construct(
        private readonly string $classes = 'border-t border-gray-200 dark:border-gray-700 my-2',
        private readonly ?Color $color = null,
    ) {
    }

    public static function make(
        string $classes = 'border-t border-gray-200 dark:border-gray-700 my-2',
        ?Color $color = null,
    ): self {
        return new self($classes, $color);
    }

    public function render(): string
    {
        $classes = $this->color === null
            ? $this->classes
            : "border-t my-2 border-{$this->color->name}-{$this->color->shade}";

        return sprintf('<hr class="%s">', htmlspecialchars($classes, ENT_QUOTES));
    }
}
