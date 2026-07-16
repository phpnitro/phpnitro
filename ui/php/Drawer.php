<?php

namespace Engine;

/**
 * Off-canvas side navigation. Zero JavaScript: a hidden checkbox + Tailwind
 * `peer` variants drive the open/close animation and the overlay, the same
 * trick as a "CSS-only" hamburger menu. Pair with DrawerToggle (usually
 * passed as AppBar's $leading) to open it.
 */
final class Drawer extends Widget
{
    /**
     * @param array<int, array{label: string, href: string}> $items
     */
    public function __construct(
        private readonly array $items,
        private readonly string $title = 'Menu',
    ) {
    }

    /**
     * @param array<int, array{label: string, href: string}> $items
     */
    public static function make(array $items, string $title = 'Menu'): self
    {
        return new self($items, $title);
    }

    public function render(): string
    {
        $links = implode('', array_map(
            static fn (array $item) => sprintf(
                '<a href="%s" class="block px-4 py-3 rounded-lg text-gray-900 dark:text-gray-100 '
                . 'hover:bg-gray-100 dark:hover:bg-gray-700">%s</a>',
                htmlspecialchars($item['href'], ENT_QUOTES),
                htmlspecialchars($item['label'], ENT_QUOTES),
            ),
            $this->items,
        ));

        $title = htmlspecialchars($this->title, ENT_QUOTES);

        return <<<HTML
        <input type="checkbox" id="phpx-drawer" class="peer hidden">
        <label for="phpx-drawer" class="hidden peer-checked:block fixed inset-0 bg-black/40 z-30" aria-hidden="true"></label>
        <nav class="gpu-layer fixed top-0 left-0 h-full w-72 max-w-[80%] bg-white dark:bg-gray-800 z-40 shadow-xl
                    -translate-x-full peer-checked:translate-x-0 transition-transform duration-200
                    flex flex-col gap-1 p-4 overflow-y-auto">
          <div class="flex items-center justify-between mb-3">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{$title}</h2>
            <label for="phpx-drawer" class="p-1 -mr-1 cursor-pointer text-gray-500 dark:text-gray-400" aria-label="Fermer">✕</label>
          </div>
          {$links}
        </nav>
        HTML;
    }
}
