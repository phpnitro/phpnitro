<?php

namespace Sdk;

class Column extends Widget
{
    /**
     * @param Widget[] $children
     */
    public static function new(array $children): static
    {
        return new static();
    }
}