<?php

namespace Sdk;

abstract class StatefulWidget extends Widget
{
    abstract public function build(): Widget;

    protected function setState(callable $updater): void
    {
    }
}
