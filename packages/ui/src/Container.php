<?php

namespace Engine;

final class Container extends Widget
{
    public function __construct(
        private readonly Widget $child,
        private readonly string $classes = 'p-4',
        private readonly ?Color $background = null,
        private readonly ?Rounded $rounded = null,
    ) {
    }

    public static function make(
        Widget $child,
        string $classes = 'p-4',
        ?Color $background = null,
        ?Rounded $rounded = null,
    ): self {
        return new self($child, $classes, $background, $rounded);
    }

    public function render(): string
    {
        return sprintf(
            '<div class="%s">%s</div>',
            htmlspecialchars($this->resolvedClasses(), ENT_QUOTES),
            $this->child->render(),
        );
    }

    /**
     * Unlike Text's typed params (which replace $classes outright — see
     * Text::resolvedClasses()), background/rounded are ADDED on top of
     * $classes instead: Container's $classes routinely carries structural
     * layout (padding, height, flex) that a typed background/rounded param
     * has no equivalent for and shouldn't discard. Passing a raw background
     * or rounded-corner Tailwind class in $classes at the same time as the
     * matching typed param is a caller error (undefined which one Tailwind
     * resolves to).
     */
    private function resolvedClasses(): string
    {
        return implode(' ', array_filter([
            $this->classes,
            $this->background?->backgroundClass(),
            $this->rounded?->value,
        ]));
    }
}
