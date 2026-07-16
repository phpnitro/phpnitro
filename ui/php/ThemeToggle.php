<?php

namespace Engine;

final class ThemeToggle extends Widget
{
    public function __construct(
        private readonly string $classes = 'text-sm text-gray-500 dark:text-gray-400 hover:underline',
    ) {
    }

    public static function make(string $classes = 'text-sm text-gray-500 dark:text-gray-400 hover:underline'): self
    {
        return new self($classes);
    }

    public function render(): string
    {
        $classes = htmlspecialchars($this->classes, ENT_QUOTES);

        return '<form method="post" class="inline">'
            . '<input type="hidden" name="_action" value="toggleTheme">'
            . Csrf::field()
            . "<button type=\"submit\" class=\"{$classes}\">Changer de thème</button>"
            . '</form>';
    }
}
