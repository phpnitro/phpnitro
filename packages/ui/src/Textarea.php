<?php

namespace Engine;

final class Textarea extends Widget
{
    private const DEFAULT_CLASSES = 'border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 '
        . 'bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 '
        . 'focus:outline-none focus:ring-2 focus:ring-blue-500';

    public function __construct(
        private readonly string $name,
        private readonly string $label = '',
        private readonly string $value = '',
        private readonly string $placeholder = '',
        private readonly int $rows = 4,
        private readonly string $classes = self::DEFAULT_CLASSES,
        private readonly string $error = '',
    ) {
    }

    public static function make(
        string $name,
        string $label = '',
        string $value = '',
        string $placeholder = '',
        int $rows = 4,
        string $classes = self::DEFAULT_CLASSES,
        string $error = '',
    ): self {
        return new self($name, $label, $value, $placeholder, $rows, $classes, $error);
    }

    public function render(): string
    {
        $classes = $this->error === '' ? $this->classes : $this->classes . ' border-red-500 dark:border-red-500 focus:ring-red-500';

        $textarea = sprintf(
            '<textarea name="%s" rows="%d" placeholder="%s" class="%s">%s</textarea>',
            htmlspecialchars($this->name, ENT_QUOTES),
            $this->rows,
            htmlspecialchars($this->placeholder, ENT_QUOTES),
            htmlspecialchars($classes, ENT_QUOTES),
            htmlspecialchars($this->value, ENT_QUOTES),
        );

        $errorHtml = $this->error === ''
            ? ''
            : sprintf('<span class="text-xs text-red-600 dark:text-red-400">%s</span>', htmlspecialchars($this->error, ENT_QUOTES));

        if ($this->label === '') {
            return $textarea . $errorHtml;
        }

        return sprintf(
            '<label class="flex flex-col gap-1 text-sm text-gray-700 dark:text-gray-300">%s%s%s</label>',
            htmlspecialchars($this->label, ENT_QUOTES),
            $textarea,
            $errorHtml,
        );
    }
}
