<?php

namespace Engine;

final class SelectBox extends Widget
{
    private const DEFAULT_CLASSES = 'border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 '
        . 'bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100';

    /**
     * @param array<string, string> $options value => label
     */
    public function __construct(
        private readonly string $name,
        private readonly array $options,
        private readonly string $selected = '',
        private readonly string $label = '',
        private readonly string $classes = self::DEFAULT_CLASSES,
    ) {
    }

    /**
     * @param array<string, string> $options value => label
     */
    public static function make(
        string $name,
        array $options,
        string $selected = '',
        string $label = '',
        string $classes = self::DEFAULT_CLASSES,
    ): self {
        return new self($name, $options, $selected, $label, $classes);
    }

    public function render(): string
    {
        $optionsHtml = '';
        foreach ($this->options as $value => $text) {
            $optionsHtml .= sprintf(
                '<option value="%s"%s>%s</option>',
                htmlspecialchars((string) $value, ENT_QUOTES),
                (string) $value === $this->selected ? ' selected' : '',
                htmlspecialchars($text, ENT_QUOTES),
            );
        }

        $select = sprintf(
            '<select name="%s" class="%s">%s</select>',
            htmlspecialchars($this->name, ENT_QUOTES),
            htmlspecialchars($this->classes, ENT_QUOTES),
            $optionsHtml,
        );

        if ($this->label === '') {
            return $select;
        }

        return sprintf(
            '<label class="flex flex-col gap-1 text-sm text-gray-700 dark:text-gray-300">%s%s</label>',
            htmlspecialchars($this->label, ENT_QUOTES),
            $select,
        );
    }
}
