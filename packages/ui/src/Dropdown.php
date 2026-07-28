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

/**
 * Click-to-open menu using the native <details>/<summary> element — no
 * JavaScript, no state to manage, works everywhere (accessible by default,
 * closes on outside click/tap in every real browser and WebView).
 */
final class Dropdown extends Widget
{
    /**
     * @param array<int, array{label: string, href: string}> $items
     */
    public function __construct(
        private readonly string $label,
        private readonly array $items,
        private readonly string $classes = 'bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100 '
            . 'font-medium px-4 py-2 rounded-lg',
        private readonly ?Color $background = null,
        private readonly ?Color $foreground = null,
    ) {
    }

    /**
     * @param array<int, array{label: string, href: string}> $items
     */
    public static function make(
        string $label,
        array $items,
        string $classes = 'bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100 font-medium px-4 py-2 rounded-lg',
        ?Color $background = null,
        ?Color $foreground = null,
    ): self {
        return new self($label, $items, $classes, $background, $foreground);
    }

    private function resolvedClasses(): string
    {
        if ($this->background === null) {
            return $this->classes;
        }

        return implode(' ', array_filter([
            $this->background->backgroundClass(),
            $this->foreground?->textClass() ?? 'text-white',
            'font-medium px-4 py-2 rounded-lg',
        ]));
    }

    public function render(): string
    {
        $links = implode('', array_map(
            static fn (array $item) => sprintf(
                '<a href="%s" class="block px-4 py-2 text-sm text-gray-900 dark:text-gray-100 '
                . 'hover:bg-gray-100 dark:hover:bg-gray-700 first:rounded-t-lg last:rounded-b-lg">%s</a>',
                htmlspecialchars($item['href'], ENT_QUOTES),
                htmlspecialchars($item['label'], ENT_QUOTES),
            ),
            $this->items,
        ));

        return sprintf(
            '<details class="relative inline-block">'
            . '<summary class="list-none flex items-center gap-1.5 cursor-pointer select-none %s">%s%s</summary>'
            . '<div class="absolute right-0 mt-1 min-w-40 bg-white dark:bg-gray-800 rounded-lg shadow-lg '
            . 'border border-gray-100 dark:border-gray-700 overflow-hidden z-20">%s</div>'
            . '</details>',
            htmlspecialchars($this->resolvedClasses(), ENT_QUOTES),
            htmlspecialchars($this->label, ENT_QUOTES),
            Icon::chevronDown(),
            $links,
        );
    }
}
