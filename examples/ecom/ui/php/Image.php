<?php

namespace Engine;

final class Image extends Widget
{
    public function __construct(
        private readonly string $src,
        private readonly string $alt = '',
        private readonly string $classes = 'max-w-full h-auto',
    ) {
    }

    public static function make(string $src, string $alt = '', string $classes = 'max-w-full h-auto'): self
    {
        return new self($src, $alt, $classes);
    }

    public function render(): string
    {
        return sprintf(
            '<img src="%s" alt="%s" class="%s">',
            htmlspecialchars($this->src, ENT_QUOTES),
            htmlspecialchars($this->alt, ENT_QUOTES),
            htmlspecialchars($this->classes, ENT_QUOTES),
        );
    }
}
