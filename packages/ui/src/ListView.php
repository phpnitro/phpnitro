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

final class ListView extends Widget
{
    private const DEFAULT_CLASSES = 'flex flex-col divide-y divide-gray-200 dark:divide-gray-700 '
        . 'bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden';

    /**
     * @param Widget[] $children
     */
    public function __construct(
        private readonly array $children,
        private readonly string $classes = self::DEFAULT_CLASSES,
        private readonly string $itemClasses = 'px-4 py-3',
    ) {
    }

    /**
     * @param Widget[] $children
     */
    public static function make(
        array $children,
        string $classes = self::DEFAULT_CLASSES,
        string $itemClasses = 'px-4 py-3',
    ): self {
        return new self($children, $classes, $itemClasses);
    }

    public function render(): string
    {
        $itemClasses = htmlspecialchars($this->itemClasses, ENT_QUOTES);

        $items = implode('', array_map(
            static fn (Widget $child) => "<div class=\"{$itemClasses}\">" . $child->render() . '</div>',
            $this->children,
        ));

        return sprintf('<div class="%s">%s</div>', htmlspecialchars($this->classes, ENT_QUOTES), $items);
    }
}
