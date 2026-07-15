<?php

namespace Engine;

abstract class Screen
{
    protected array $state;

    public function __construct()
    {
        $this->state = $_SESSION[static::class] ?? $this->initialState();
    }

    abstract protected function initialState(): array;

    abstract public function build(): Widget;

    public function handle(string $action): void
    {
        $method = 'on' . ucfirst($action);

        if (method_exists($this, $method)) {
            $this->$method();
        }

        $_SESSION[static::class] = $this->state;
    }
}
