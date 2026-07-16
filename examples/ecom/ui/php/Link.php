<?php

namespace Engine;

final class Link extends Widget
{
    public function __construct(
        private readonly string $label,
        private readonly string $href,
        private readonly string $classes = 'text-blue-600 hover:underline',
    ) {
    }

    public static function make(string $label, string $href, string $classes = 'text-blue-600 hover:underline'): self
    {
        return new self($label, $href, $classes);
    }

    public function render(): string
    {
        return sprintf(
            '<a href="%s" class="%s">%s</a>',
            htmlspecialchars($this->href, ENT_QUOTES),
            htmlspecialchars($this->classes, ENT_QUOTES),
            htmlspecialchars($this->label, ENT_QUOTES),
        );
    }
}
