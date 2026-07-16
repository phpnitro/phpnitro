<?php

namespace Engine;

/**
 * Flutter's Padding takes an EdgeInsets; here $classes is any Tailwind
 * spacing utility ('p-4', 'px-6 py-2', 'pt-8'...) — same idea, DOM-native
 * syntax instead of a dedicated value object.
 */
final class Padding extends Widget
{
    public function __construct(
        private readonly Widget $child,
        private readonly string $classes = 'p-4',
    ) {
    }

    public static function make(Widget $child, string $classes = 'p-4'): self
    {
        return new self($child, $classes);
    }

    public function render(): string
    {
        return sprintf('<div class="%s">%s</div>', htmlspecialchars($this->classes, ENT_QUOTES), $this->child->render());
    }
}
