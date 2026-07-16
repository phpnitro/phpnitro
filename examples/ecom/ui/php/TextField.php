<?php

namespace Engine;

final class TextField extends Widget
{
    private const DEFAULT_CLASSES = 'border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 '
        . 'bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 '
        . 'focus:outline-none focus:ring-2 focus:ring-blue-500';

    public function __construct(
        private readonly string $name,
        private readonly string $label = '',
        private readonly string $value = '',
        private readonly string $type = 'text',
        private readonly string $placeholder = '',
        private readonly string $classes = self::DEFAULT_CLASSES,
    ) {
    }

    public static function make(
        string $name,
        string $label = '',
        string $value = '',
        string $type = 'text',
        string $placeholder = '',
        string $classes = self::DEFAULT_CLASSES,
    ): self {
        return new self($name, $label, $value, $type, $placeholder, $classes);
    }

    public function render(): string
    {
        $input = sprintf(
            '<input type="%s" name="%s" value="%s" placeholder="%s" class="%s">',
            htmlspecialchars($this->type, ENT_QUOTES),
            htmlspecialchars($this->name, ENT_QUOTES),
            htmlspecialchars($this->value, ENT_QUOTES),
            htmlspecialchars($this->placeholder, ENT_QUOTES),
            htmlspecialchars($this->classes, ENT_QUOTES),
        );

        if ($this->label === '') {
            return $input;
        }

        return sprintf(
            '<label class="flex flex-col gap-1 text-sm text-gray-700 dark:text-gray-300">%s%s</label>',
            htmlspecialchars($this->label, ENT_QUOTES),
            $input,
        );
    }
}
