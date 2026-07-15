<?php

namespace Sdk;

abstract class StatelessWidget extends Widget
{
    abstract public function build(): Widget;
}
