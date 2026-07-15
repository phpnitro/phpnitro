<?php

namespace Engine;

final class Button extends Widget
{
    private const DEFAULT_CLASSES = 'bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded-lg transition-colors';

    public function __construct(
        private readonly string $label,
        private readonly string $classes = self::DEFAULT_CLASSES,
    ) {
    }

    public static function make(string $label, string $classes = self::DEFAULT_CLASSES): self
    {
        return new self($label, $classes);
    }

    public function render(): string
    {
        return sprintf(
            '<button class="%s">%s</button>',
            htmlspecialchars($this->classes, ENT_QUOTES),
            htmlspecialchars($this->label, ENT_QUOTES),
        );
    }
}
