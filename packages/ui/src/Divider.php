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
    ) {
    }

    public static function make(string $classes = 'border-t border-gray-200 dark:border-gray-700 my-2'): self
    {
        return new self($classes);
    }

    public function render(): string
    {
        return sprintf('<hr class="%s">', htmlspecialchars($this->classes, ENT_QUOTES));
    }
}
