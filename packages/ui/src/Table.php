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

final class Table extends Widget
{
    /**
     * @param array<int, array<int, string|Widget>> $rows
     * @param array<int, string> $headers
     */
    public function __construct(
        private readonly array $rows,
        private readonly array $headers = [],
        private readonly string $border = TableBorder::ALL,
        private readonly string $classes = 'w-full text-left text-sm text-gray-700 dark:text-gray-300',
    ) {
    }

    /**
     * @param array<int, array<int, string|Widget>> $rows
     * @param array<int, string> $headers
     */
    public static function make(
        array $rows,
        array $headers = [],
        string $border = TableBorder::ALL,
        string $classes = 'w-full text-left text-sm text-gray-700 dark:text-gray-300',
    ): self {
        return new self($rows, $headers, $border, $classes);
    }

    public function render(): string
    {
        $thead = '';
        if ($this->headers !== []) {
            $cells = implode('', array_map(
                fn (string $header) => sprintf('<th class="px-3 py-2 font-semibold">%s</th>', htmlspecialchars($header, ENT_QUOTES)),
                $this->headers,
            ));
            $thead = "<thead><tr>{$cells}</tr></thead>";
        }

        $tbody = implode('', array_map(function (array $row): string {
            $cells = implode('', array_map(
                fn (string|Widget $cell) => sprintf('<td class="px-3 py-2">%s</td>', $cell instanceof Widget ? $cell->render() : htmlspecialchars($cell, ENT_QUOTES)),
                $row,
            ));

            return "<tr>{$cells}</tr>";
        }, $this->rows));

        $classes = htmlspecialchars(trim($this->classes . ' ' . $this->border), ENT_QUOTES);

        return "<table class=\"{$classes}\">{$thead}<tbody>{$tbody}</tbody></table>";
    }
}
