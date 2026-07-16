<?php

namespace Engine;

final class FloatingActionButton extends Widget
{
    use RendersAction;

    private const DEFAULT_CLASSES = 'gpu-layer fixed bottom-20 right-4 w-14 h-14 rounded-full bg-blue-600 hover:bg-blue-700 '
        . 'text-white text-2xl leading-none flex items-center justify-center shadow-lg';

    public function __construct(
        private readonly string $label,
        private readonly ?string $action = null,
        private readonly string $classes = self::DEFAULT_CLASSES,
    ) {
    }

    public static function make(string $label, ?string $action = null, string $classes = self::DEFAULT_CLASSES): self
    {
        return new self($label, $action, $classes);
    }

    public function render(): string
    {
        return $this->renderActionableButton($this->label, $this->action, $this->classes);
    }
}
