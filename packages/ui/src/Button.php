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

final class Button extends Widget
{
    use RendersAction;

    private const DEFAULT_CLASSES = 'bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded-lg transition-colors';

    public function __construct(
        private readonly string $label,
        private readonly ?string $action = null,
        private readonly string $classes = self::DEFAULT_CLASSES,
        private readonly ?string $onClick = null,
        private readonly ?Color $background = null,
        private readonly ?Color $foreground = null,
    ) {
    }

    public static function make(
        string $label,
        ?string $action = null,
        string $classes = self::DEFAULT_CLASSES,
        ?string $onClick = null,
        ?Color $background = null,
        ?Color $foreground = null,
    ): self {
        return new self($label, $action, $classes, $onClick, $background, $foreground);
    }

    public function render(): string
    {
        return $this->renderActionableButton($this->label, $this->action, $this->resolvedClasses(), $this->onClick);
    }

    /**
     * $background alone replaces $classes' color/hover pair (not just
     * added on top, unlike Container's background/rounded): a button's
     * base+hover shades are coupled (hover: same hue, one step darker), so
     * "add background on top of $classes" would leave the OLD hover color
     * fighting the new base color instead of moving with it. $foreground
     * defaults to white — the near-universal choice for a solid Tailwind
     * button background, overridable for a light background needing dark
     * text.
     */
    private function resolvedClasses(): string
    {
        if ($this->background === null) {
            return $this->classes;
        }

        $hoverShade = min($this->background->shade + 100, 900);
        $hover = Color::of($this->background->name, $hoverShade)->backgroundClass();
        // Tailwind's white/black utilities have no shade suffix (`text-white`,
        // never `text-white-0`) — Color::textClass() always appends one, so
        // the default has to be a literal string here, not Color::of('white', 0).
        $foregroundClass = $this->foreground?->textClass() ?? 'text-white';

        return implode(' ', [
            $this->background->backgroundClass(),
            "hover:{$hover}",
            $foregroundClass,
            'font-medium px-4 py-2 rounded-lg transition-colors',
        ]);
    }
}
