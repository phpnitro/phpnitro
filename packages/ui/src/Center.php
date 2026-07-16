<?php

namespace Engine;

final class Center extends Widget
{
    public function __construct(private readonly Widget $child)
    {
    }

    public static function make(Widget $child): self
    {
        return new self($child);
    }

    public function render(): string
    {
        return '<div class="flex items-center justify-center w-full h-full">' . $this->child->render() . '</div>';
    }
}
