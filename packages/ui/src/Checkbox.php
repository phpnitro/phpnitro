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

final class Checkbox extends Widget
{
    public function __construct(
        private readonly string $name,
        private readonly string $label,
        private readonly bool $checked = false,
        private readonly ?Color $accentColor = null,
    ) {
    }

    public static function make(string $name, string $label, bool $checked = false, ?Color $accentColor = null): self
    {
        return new self($name, $label, $checked, $accentColor);
    }

    public function render(): string
    {
        $color = $this->accentColor ?? Theme::primary();
        $accentClass = "accent-{$color->name}-{$color->shade}";

        return sprintf(
            '<label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">'
            . '<input type="checkbox" name="%s" value="1"%s class="w-4 h-4 rounded %s">%s</label>',
            htmlspecialchars($this->name, ENT_QUOTES),
            $this->checked ? ' checked' : '',
            htmlspecialchars($accentClass, ENT_QUOTES),
            htmlspecialchars($this->label, ENT_QUOTES),
        );
    }
}
