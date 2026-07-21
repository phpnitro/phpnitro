<?php

namespace Engine;

/**
 * Only meaningful as a direct child of Stack — gives that child an explicit
 * offset (top/right/bottom/left, in pixels) instead of stretching to fill
 * the stack. Inline style rather than Tailwind classes: these are arbitrary
 * runtime values Tailwind's build-time class scanner can never see (unlike
 * Container's Color/Rounded, which only ever emit a small fixed set of
 * class names that already exist elsewhere in the compiled stylesheet).
 */
final class Positioned extends Widget
{
    public function __construct(
        private readonly Widget $child,
        private readonly ?int $top = null,
        private readonly ?int $right = null,
        private readonly ?int $bottom = null,
        private readonly ?int $left = null,
    ) {
    }

    public static function make(
        Widget $child,
        ?int $top = null,
        ?int $right = null,
        ?int $bottom = null,
        ?int $left = null,
    ): self {
        return new self($child, $top, $right, $bottom, $left);
    }

    public function render(): string
    {
        $style = implode('', array_filter([
            $this->top !== null ? "top:{$this->top}px;" : '',
            $this->right !== null ? "right:{$this->right}px;" : '',
            $this->bottom !== null ? "bottom:{$this->bottom}px;" : '',
            $this->left !== null ? "left:{$this->left}px;" : '',
        ]));

        return sprintf('<div class="absolute" style="%s">%s</div>', $style, $this->child->render());
    }
}
