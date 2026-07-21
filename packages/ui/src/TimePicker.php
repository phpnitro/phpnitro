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

/**
 * input[type=time] — same reasoning as DatePicker: WebView delegates to
 * the OS native time-picker dialog.
 */
final class TimePicker extends Widget
{
    private const DEFAULT_CLASSES = 'border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 '
        . 'bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 '
        . 'focus:outline-none focus:ring-2 focus:ring-blue-500';

    public function __construct(
        private readonly string $name,
        private readonly string $label = '',
        private readonly string $value = '',
        private readonly string $classes = self::DEFAULT_CLASSES,
    ) {
    }

    public static function make(
        string $name,
        string $label = '',
        string $value = '',
        string $classes = self::DEFAULT_CLASSES,
    ): self {
        return new self($name, $label, $value, $classes);
    }

    public function render(): string
    {
        $input = sprintf(
            '<input type="time" name="%s" value="%s" class="%s">',
            htmlspecialchars($this->name, ENT_QUOTES),
            htmlspecialchars($this->value, ENT_QUOTES),
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
