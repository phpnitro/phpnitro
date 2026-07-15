<?php

namespace Sdk;

class SizedBox extends Widget
{
    public static function new(?Widget $child = null, ?float $width = null, ?float $height = null): static
    {
        return new static();
    }
}
