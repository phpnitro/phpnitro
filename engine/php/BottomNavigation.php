<?php

namespace Engine;

final class BottomNavigation extends Widget
{
    private const DEFAULT_CLASSES = 'fixed bottom-0 left-0 right-0 flex justify-around items-center '
        . 'bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 py-2';

    /**
     * @param array<int, array{label: string, href: string}> $items
     */
    public function __construct(
        private readonly array $items,
        private readonly string $classes = self::DEFAULT_CLASSES,
    ) {
    }

    /**
     * @param array<int, array{label: string, href: string}> $items
     */
    public static function make(array $items, string $classes = self::DEFAULT_CLASSES): self
    {
        return new self($items, $classes);
    }

    public function render(): string
    {
        $links = implode('', array_map(
            static fn (array $item) => sprintf(
                '<a href="%s" class="text-sm text-gray-600 dark:text-gray-300 hover:text-blue-600">%s</a>',
                htmlspecialchars($item['href'], ENT_QUOTES),
                htmlspecialchars($item['label'], ENT_QUOTES),
            ),
            $this->items,
        ));

        return sprintf('<nav class="%s">%s</nav>', htmlspecialchars($this->classes, ENT_QUOTES), $links);
    }
}
