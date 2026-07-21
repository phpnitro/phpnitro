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
 * infinite_scroll_pagination equivalent. $endpoint gets "?page=N" appended
 * by assets/js/infinite-scroll.js as the user scrolls near the bottom
 * (IntersectionObserver on a sentinel element); the endpoint returns raw
 * HTML for that page (empty body = no more pages), same "PHP renders,
 * JS just swaps/appends" idiom as StreamBuilder/FutureBuilder.
 *
 * @param Widget[] $initialItems
 */
final class InfiniteScrollList extends Widget
{
    /**
     * @param Widget[] $initialItems
     */
    public function __construct(
        private readonly string $endpoint,
        private readonly array $initialItems,
        private readonly string $classes = 'flex flex-col gap-2',
    ) {
    }

    /**
     * @param Widget[] $initialItems
     */
    public static function make(string $endpoint, array $initialItems, string $classes = 'flex flex-col gap-2'): self
    {
        return new self($endpoint, $initialItems, $classes);
    }

    public function render(): string
    {
        $items = implode('', array_map(static fn (Widget $item) => $item->render(), $this->initialItems));

        return sprintf(
            '<div data-infinite-scroll-list data-endpoint="%s" class="%s">%s<div data-infinite-scroll-sentinel class="h-1"></div></div>',
            htmlspecialchars($this->endpoint, ENT_QUOTES),
            htmlspecialchars($this->classes, ENT_QUOTES),
            $items,
        );
    }
}
