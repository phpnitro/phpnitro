<?php

namespace Engine;

/**
 * Like Row, but children wrap onto a new line instead of overflowing —
 * Tailwind's flex-wrap, the same idea as Flutter's Wrap.
 */
final class Wrap extends Widget
{
    /**
     * @param Widget[] $children
     */
    public function __construct(
        private readonly array $children,
        private readonly string $classes = 'flex flex-wrap gap-3',
    ) {
    }

    /**
     * @param Widget[] $children
     */
    public static function make(array $children, string $classes = 'flex flex-wrap gap-3'): self
    {
        return new self($children, $classes);
    }

    public function render(): string
    {
        $inner = implode('', array_map(static fn (Widget $child) => $child->render(), $this->children));

        return sprintf('<div class="%s">%s</div>', htmlspecialchars($this->classes, ENT_QUOTES), $inner);
    }
}
