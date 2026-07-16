<?php

namespace Engine;

/**
 * Flutter's PageView is a swipeable page carousel with snap-to-page
 * behavior — the DOM equivalent is a horizontally scrollable flex row
 * with CSS scroll-snap, native to WebView, no JS needed for the swipe
 * gesture itself.
 */
final class PageView extends Widget
{
    /** @param Widget[] $pages */
    public function __construct(
        private readonly array $pages,
        private readonly string $classes = 'flex overflow-x-auto snap-x snap-mandatory w-full',
        private readonly string $pageClasses = 'snap-center shrink-0 w-full',
    ) {
    }

    /** @param Widget[] $pages */
    public static function make(
        array $pages,
        string $classes = 'flex overflow-x-auto snap-x snap-mandatory w-full',
        string $pageClasses = 'snap-center shrink-0 w-full',
    ): self {
        return new self($pages, $classes, $pageClasses);
    }

    public function render(): string
    {
        $pageClasses = htmlspecialchars($this->pageClasses, ENT_QUOTES);
        $pagesHtml = implode('', array_map(
            fn (Widget $page) => sprintf('<div class="%s">%s</div>', $pageClasses, $page->render()),
            $this->pages,
        ));

        return sprintf('<div class="%s">%s</div>', htmlspecialchars($this->classes, ENT_QUOTES), $pagesHtml);
    }
}
