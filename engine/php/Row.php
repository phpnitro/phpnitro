<?php

namespace Engine;

final class Row extends Widget
{
    /**
     * @param Widget[] $children
     */
    public function __construct(
        private readonly array $children,
        private readonly string $classes = 'flex flex-row gap-3 items-center',
    ) {
    }

    /**
     * @param Widget[] $children
     */
    public static function make(array $children, string $classes = 'flex flex-row gap-3 items-center'): self
    {
        return new self($children, $classes);
    }

    public function render(): string
    {
        $inner = implode('', array_map(static fn (Widget $child) => $child->render(), $this->children));

        return sprintf('<div class="%s">%s</div>', htmlspecialchars($this->classes, ENT_QUOTES), $inner);
    }
}
