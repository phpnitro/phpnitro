<?php

namespace Engine;

final class Align extends Widget
{
    public function __construct(
        private readonly Widget $child,
        private readonly string $alignment = Alignment::CENTER,
    ) {
    }

    public static function make(Widget $child, string $alignment = Alignment::CENTER): self
    {
        return new self($child, $alignment);
    }

    public function render(): string
    {
        return sprintf(
            '<div class="flex w-full h-full %s">%s</div>',
            htmlspecialchars($this->alignment, ENT_QUOTES),
            $this->child->render(),
        );
    }
}
