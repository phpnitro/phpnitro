<?php

namespace Engine;

final class BottomNavigation extends Widget
{
    public const VARIANT_DEFAULT = 'default';
    public const VARIANT_COMPACT = 'compact';
    public const VARIANT_PILLS = 'pills';

    /**
     * @param array<int, array{label: string, href: string, icon?: string}> $items
     */
    public function __construct(
        private readonly array $items,
        private readonly string $variant = self::VARIANT_DEFAULT,
    ) {
    }

    /**
     * @param array<int, array{label: string, href: string, icon?: string}> $items
     */
    public static function make(array $items, string $variant = self::VARIANT_DEFAULT): self
    {
        return new self($items, $variant);
    }

    public function render(): string
    {
        $currentPath = $this->normalizePath(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

        $items = implode('', array_map(
            fn (array $item) => $this->renderItem($item, $this->normalizePath($item['href']) === $currentPath),
            $this->items,
        ));

        return sprintf('<nav class="%s">%s</nav>', $this->navClasses(), $items);
    }

    private function normalizePath(string $path): string
    {
        $trimmed = rtrim($path, '/');

        return $trimmed === '' ? '/' : $trimmed;
    }

    private function navClasses(): string
    {
        return match ($this->variant) {
            self::VARIANT_PILLS => 'gpu-layer fixed bottom-3 left-3 right-3 flex justify-around items-center gap-1 '
                . 'bg-white/95 dark:bg-gray-800/95 backdrop-blur rounded-full shadow-lg '
                . 'border border-gray-100 dark:border-gray-700 px-2 py-1.5',
            self::VARIANT_COMPACT => 'gpu-layer fixed bottom-0 left-0 right-0 flex justify-around items-center '
                . 'bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 py-1',
            default => 'gpu-layer fixed bottom-0 left-0 right-0 flex justify-around items-stretch '
                . 'bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 shadow-lg',
        };
    }

    /**
     * @param array{label: string, href: string, icon?: string} $item
     */
    private function renderItem(array $item, bool $active): string
    {
        $href = htmlspecialchars($item['href'], ENT_QUOTES);
        $label = htmlspecialchars($item['label'], ENT_QUOTES);
        $icon = $item['icon'] ?? '•';

        $activeClasses = $active
            ? 'text-blue-600 dark:text-blue-400'
            : 'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200';

        if ($this->variant === self::VARIANT_PILLS) {
            $pillBg = $active ? 'bg-blue-50 dark:bg-blue-950' : '';

            return sprintf(
                '<a href="%s" class="flex flex-col items-center justify-center gap-0.5 rounded-full px-3 py-1.5 text-[11px] font-medium transition-colors %s %s">'
                . '<span class="text-base leading-none">%s</span><span>%s</span>'
                . '</a>',
                $href,
                $activeClasses,
                $pillBg,
                $icon,
                $label,
            );
        }

        if ($this->variant === self::VARIANT_COMPACT) {
            return sprintf(
                '<a href="%s" class="flex items-center justify-center p-3 text-xl transition-colors %s" title="%s">%s</a>',
                $href,
                $activeClasses,
                $label,
                $icon,
            );
        }

        return sprintf(
            '<a href="%s" class="flex flex-1 flex-col items-center justify-center gap-0.5 py-2 text-xs font-medium transition-colors %s">'
            . '<span class="text-lg leading-none">%s</span><span>%s</span>'
            . '</a>',
            $href,
            $activeClasses,
            $icon,
            $label,
        );
    }
}
