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
        private readonly ?Color $activeColor = null,
    ) {
    }

    /**
     * @param array<int, array{label: string, href: string, icon?: string}> $items
     */
    public static function make(array $items, string $variant = self::VARIANT_DEFAULT, ?Color $activeColor = null): self
    {
        return new self($items, $variant, $activeColor);
    }

    public function render(): string
    {
        $currentPath = $this->normalizePath(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

        $items = implode('', array_map(
            fn (array $item) => $this->renderItem($item, $this->normalizePath($item['href']) === $currentPath),
            $this->items,
        ));

        return sprintf('<nav id="phpx-bottom-nav" class="%s">%s</nav>', $this->navClasses(), $items);
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
     * The nav bar is rendered once and never re-created (see PageRenderer/
     * nav.js) — after a partial navigation swaps the content, nothing
     * re-runs this PHP to recompute which tab is active. Each link carries
     * its own active/inactive class string as data attributes so nav.js
     * can toggle `class` directly (a plain string swap) without knowing
     * anything about this variant's Tailwind classes.
     *
     * @param array{label: string, href: string, icon?: string} $item
     */
    private function renderItem(array $item, bool $active): string
    {
        $href = htmlspecialchars($item['href'], ENT_QUOTES);
        $label = htmlspecialchars($item['label'], ENT_QUOTES);
        $icon = $item['icon'] ?? '•';

        [$base, $activeClasses, $inactiveClasses] = $this->itemClassParts();
        $activeClass = htmlspecialchars(trim("{$base} {$activeClasses}"), ENT_QUOTES);
        $inactiveClass = htmlspecialchars(trim("{$base} {$inactiveClasses}"), ENT_QUOTES);
        $currentClass = $active ? $activeClass : $inactiveClass;

        $title = $this->variant === self::VARIANT_COMPACT ? sprintf(' title="%s"', $label) : '';
        $inner = $this->variant === self::VARIANT_COMPACT
            ? $icon
            : sprintf('<span class="%s">%s</span><span>%s</span>', $this->variant === self::VARIANT_PILLS ? 'text-base leading-none' : 'text-lg leading-none', $icon, $label);

        return sprintf(
            '<a href="%s" class="%s" data-active-class="%s" data-inactive-class="%s"%s>%s</a>',
            $href,
            $currentClass,
            $activeClass,
            $inactiveClass,
            $title,
            $inner,
        );
    }

    /**
     * $activeColor only overrides the light-mode shade — the dark-mode
     * pairing (blue-400/blue-950) and the pills' light background shade
     * (blue-50) aren't a fixed offset from an arbitrary base shade the way
     * Button's hover shade is, so they're left as the framework default
     * rather than guessing a mapping that could come out wrong in dark
     * mode — deliberately NOT wired to Theme::primary() by default for that
     * reason (unlike FloatingActionButton/ProgressBar), only settable
     * explicitly per call.
     *
     * @return array{0: string, 1: string, 2: string} base classes, classes added when active, classes added when inactive
     */
    private function itemClassParts(): array
    {
        $activeColorClass = $this->activeColor?->textClass();

        return match ($this->variant) {
            self::VARIANT_PILLS => [
                'flex flex-col items-center justify-center gap-0.5 rounded-full px-3 py-1.5 text-[11px] font-medium transition-colors',
                ($activeColorClass ?? 'text-blue-600') . ' dark:text-blue-400 bg-blue-50 dark:bg-blue-950',
                'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200',
            ],
            self::VARIANT_COMPACT => [
                'flex items-center justify-center p-3 text-xl transition-colors',
                ($activeColorClass ?? 'text-blue-600') . ' dark:text-blue-400',
                'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200',
            ],
            default => [
                'flex flex-1 flex-col items-center justify-center gap-0.5 py-2 text-xs font-medium transition-colors',
                ($activeColorClass ?? 'text-blue-600') . ' dark:text-blue-400',
                'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200',
            ],
        };
    }
}
