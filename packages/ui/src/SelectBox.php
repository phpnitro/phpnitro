<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
        private readonly string $error = '',
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
        string $error = '',
    ): self {
        return new self($name, $options, $selected, $label, $classes, $error);
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

        $classes = $this->error === '' ? $this->classes : $this->classes . ' border-red-500 dark:border-red-500';

        $select = sprintf(
            '<select name="%s" class="%s">%s</select>',
            htmlspecialchars($this->name, ENT_QUOTES),
            htmlspecialchars($classes, ENT_QUOTES),
            $optionsHtml,
        );

        $errorHtml = $this->error === ''
            ? ''
            : sprintf('<span class="text-xs text-red-600 dark:text-red-400">%s</span>', htmlspecialchars($this->error, ENT_QUOTES));

        if ($this->label === '') {
            return $select . $errorHtml;
        }

        return sprintf(
            '<label class="flex flex-col gap-1 text-sm text-gray-700 dark:text-gray-300">%s%s%s</label>',
            htmlspecialchars($this->label, ENT_QUOTES),
            $select,
            $errorHtml,
        );
    }
}
