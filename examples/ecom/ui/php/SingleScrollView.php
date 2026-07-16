<?php

namespace Engine;

final class SingleScrollView extends Widget
{
    public function __construct(
        private readonly Widget $child,
        private readonly string $classes = 'overflow-y-auto max-h-screen',
    ) {
    }

    public static function make(Widget $child, string $classes = 'overflow-y-auto max-h-screen'): self
    {
        return new self($child, $classes);
    }

    public function render(): string
    {
        return sprintf(
            '<div class="%s">%s</div>',
            htmlspecialchars($this->classes, ENT_QUOTES),
            $this->child->render(),
        );
    }
}
