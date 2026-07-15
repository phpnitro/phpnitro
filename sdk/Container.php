<?php

namespace Sdk;

class Container extends Widget
{
    public static function new(Widget $child, ?string $color = null): static
    {
        return new static();
    }
}
