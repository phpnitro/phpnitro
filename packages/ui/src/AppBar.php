<?php

namespace Engine;

final class AppBar extends Widget
{
    private const DEFAULT_CLASSES = 'gpu-layer fixed top-0 left-0 right-0 z-10 flex items-center gap-3 px-4 h-14 '
        . 'bg-white/95 dark:bg-gray-800/95 backdrop-blur border-b border-gray-200 dark:border-gray-700';

    public function __construct(
        private readonly string $title,
        private readonly ?string $backHref = null,
        private readonly string $classes = self::DEFAULT_CLASSES,
        private readonly ?Widget $leading = null,
    ) {
    }

    public static function make(
        string $title,
        ?string $backHref = null,
        string $classes = self::DEFAULT_CLASSES,
        ?Widget $leading = null,
    ): self {
        return new self($title, $backHref, $classes, $leading);
    }

    public function render(): string
    {
        $leading = match (true) {
            $this->leading !== null => $this->leading->render(),
            $this->backHref !== null => sprintf(
                '<a href="%s" class="text-gray-600 dark:text-gray-300 -ml-1 p-1" aria-label="Retour">'
                . '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-6 h-6" aria-hidden="true">'
                . '<polyline points="14,6 8,12 14,18" fill="none" stroke="currentColor" stroke-width="2" '
                . 'stroke-linecap="round" stroke-linejoin="round"/></svg></a>',
                htmlspecialchars($this->backHref, ENT_QUOTES),
            ),
            default => '',
        };

        return sprintf(
            '<header class="%s">%s<h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">%s</h1></header>',
            htmlspecialchars($this->classes, ENT_QUOTES),
            $leading,
            htmlspecialchars($this->title, ENT_QUOTES),
        );
    }
}
