<?php

namespace Engine;

final class SwitchToggle extends Widget
{
    public function __construct(
        private readonly string $name,
        private readonly string $label,
        private readonly bool $on = false,
    ) {
    }

    public static function make(string $name, string $label, bool $on = false): self
    {
        return new self($name, $label, $on);
    }

    public function render(): string
    {
        return sprintf(
            '<label class="flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">'
            . '<input type="checkbox" name="%s" value="1"%s class="peer sr-only">'
            . '<span class="w-10 h-6 rounded-full bg-gray-300 dark:bg-gray-600 peer-checked:bg-blue-600 '
            . 'relative transition-colors after:content-[\'\'] after:absolute after:top-0.5 after:left-0.5 '
            . 'after:w-5 after:h-5 after:rounded-full after:bg-white after:transition-transform '
            . 'peer-checked:after:translate-x-4"></span>%s</label>',
            htmlspecialchars($this->name, ENT_QUOTES),
            $this->on ? ' checked' : '',
            htmlspecialchars($this->label, ENT_QUOTES),
        );
    }
}
