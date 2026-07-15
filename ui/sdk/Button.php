<?php

namespace Sdk;

class Button extends Widget
{
    public static function new(string $label): static
    {
        return new static();
    }
}
