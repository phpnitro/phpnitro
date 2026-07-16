<?php

namespace Engine;

final class Divider extends Widget
{
    public function __construct(
        private readonly string $classes = 'border-t border-gray-200 dark:border-gray-700 my-2',
    ) {
    }

    public static function make(string $classes = 'border-t border-gray-200 dark:border-gray-700 my-2'): self
    {
        return new self($classes);
    }

    public function render(): string
    {
        return sprintf('<hr class="%s">', htmlspecialchars($this->classes, ENT_QUOTES));
    }
}
