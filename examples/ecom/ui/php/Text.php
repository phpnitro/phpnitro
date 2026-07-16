<?php

namespace Engine;

final class Text extends Widget
{
    private const DEFAULT_CLASSES = 'text-base text-gray-900 dark:text-gray-100';

    public function __construct(
        private readonly string $content,
        private readonly string $classes = self::DEFAULT_CLASSES,
        private readonly ?TextSize $size = null,
        private readonly ?FontWeight $weight = null,
        private readonly ?Color $color = null,
    ) {
    }

    public static function make(
        string $content,
        string $classes = self::DEFAULT_CLASSES,
        ?TextSize $size = null,
        ?FontWeight $weight = null,
        ?Color $color = null,
    ): self {
        return new self($content, $classes, $size, $weight, $color);
    }

    public function render(): string
    {
        return sprintf(
            '<p class="%s">%s</p>',
            htmlspecialchars($this->resolvedClasses(), ENT_QUOTES),
            htmlspecialchars($this->content, ENT_QUOTES),
        );
    }

    /**
     * If any typed style param is given, it drives the class list (so
     * `size:`/`weight:`/`color:` behave predictably, without depending on
     * Tailwind's generated stylesheet order to resolve a conflict with the
     * $classes default). Otherwise falls back to the raw $classes string —
     * still the escape hatch for anything the typed API doesn't cover yet.
     */
    private function resolvedClasses(): string
    {
        if ($this->size === null && $this->weight === null && $this->color === null) {
            return $this->classes;
        }

        return implode(' ', array_filter([
            $this->size?->value,
            $this->weight?->value,
            $this->color?->textClass(),
        ]));
    }
}
