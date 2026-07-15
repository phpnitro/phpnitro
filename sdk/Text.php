<?php

namespace Sdk;

class Text extends Widget
{
    public static function new(string $text): static
    {
        return new static();
    }
}