<?php

namespace Engine;

final class Margin extends Widget
{
    public function __construct(
        private readonly Widget $child,
        private readonly string $classes = 'm-4',
    ) {
    }

    public static function make(Widget $child, string $classes = 'm-4'): self
    {
        return new self($child, $classes);
    }

    public function render(): string
    {
        return sprintf('<div class="%s">%s</div>', htmlspecialchars($this->classes, ENT_QUOTES), $this->child->render());
    }
}
