<?php

namespace Engine;

/**
 * Overlays children on top of each other. A plain (non-Positioned) child
 * stretches to fill the stack (absolute inset-0); a Positioned child renders
 * at its own explicit offset instead — the same Stack/Positioned pairing
 * Flutter uses, built here on plain CSS absolute positioning, not a
 * dedicated layout/paint engine (see ROADMAP-PARITE-FLUTTER-REACT-NATIVE.md
 * item #5).
 */
final class Stack extends Widget
{
    /**
     * @param Widget[] $children
     */
    public function __construct(
        private readonly array $children,
        private readonly string $classes = 'relative',
    ) {
    }

    /**
     * @param Widget[] $children
     */
    public static function make(array $children, string $classes = 'relative'): self
    {
        return new self($children, $classes);
    }

    public function render(): string
    {
        $inner = implode('', array_map(
            static fn (Widget $child) => $child instanceof Positioned
                ? $child->render()
                : sprintf('<div class="absolute inset-0">%s</div>', $child->render()),
            $this->children,
        ));

        return sprintf('<div class="%s">%s</div>', htmlspecialchars($this->classes, ENT_QUOTES), $inner);
    }
}
