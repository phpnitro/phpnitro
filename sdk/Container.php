<?php

namespace Sdk;

class Container extends Widget
{
    public static function new(Widget $child): static
    {
        return new static();
    }
}
