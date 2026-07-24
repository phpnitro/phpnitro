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

final class FloatingActionButton extends Widget
{
    use RendersAction;

    private const BASE_CLASSES = 'gpu-layer fixed bottom-20 right-4 w-14 h-14 rounded-full '
        . 'text-white text-2xl leading-none flex items-center justify-center shadow-lg';

    public function __construct(
        private readonly string $label,
        private readonly ?string $action = null,
        private readonly ?string $classes = null,
        private readonly string $ariaLabel = '',
        private readonly ?Color $background = null,
    ) {
    }

    public static function make(
        string $label,
        ?string $action = null,
        ?string $classes = null,
        string $ariaLabel = '',
        ?Color $background = null,
    ): self {
        return new self($label, $action, $classes, $ariaLabel, $background);
    }

    public function render(): string
    {
        // $label is usually a bare glyph ("+", "✎"...) — real text, but not
        // a meaningful accessible name on its own (confirmed with a real
        // TalkBack accessibility dump: the FAB was announced as literally
        // "plus"). $ariaLabel lets a caller supply what should actually be
        // spoken instead, same idiom as IconButton's $ariaLabel.
        return $this->renderActionableButton($this->label, $this->action, $this->resolvedClasses(), ariaLabel: $this->ariaLabel);
    }

    /**
     * $classes (raw override) wins outright when given, same escape hatch
     * as everywhere else. Otherwise the color comes from $background if
     * given, else Theme::primary() — so a project-wide Theme::setPrimary()
     * recolors every FAB without touching call sites, but an explicit
     * per-call $background still wins over the theme.
     */
    private function resolvedClasses(): string
    {
        if ($this->classes !== null) {
            return $this->classes;
        }

        $color = $this->background ?? Theme::primary();
        $hoverShade = min($color->shade + 100, 900);
        $hover = Color::of($color->name, $hoverShade)->backgroundClass();

        return self::BASE_CLASSES . ' ' . $color->backgroundClass() . " hover:{$hover}";
    }
}
