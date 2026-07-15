<?php

namespace Engine;

final class Text extends Widget
{
    public function __construct(
        private readonly string $content,
        private readonly string $classes = 'text-base text-gray-900 dark:text-gray-100',
    ) {
    }

    public static function make(string $content, string $classes = 'text-base text-gray-900 dark:text-gray-100'): self
    {
        return new self($content, $classes);
    }

    public function render(): string
    {
        return sprintf(
            '<p class="%s">%s</p>',
            htmlspecialchars($this->classes, ENT_QUOTES),
            htmlspecialchars($this->content, ENT_QUOTES),
        );
    }
}
