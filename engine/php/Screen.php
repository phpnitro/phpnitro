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

        if (!method_exists($this, $method)) {
            throw new \RuntimeException("Unknown action \"{$action}\" for screen " . static::class);
        }

        $this->$method();
        $_SESSION[static::class] = $this->state;
    }
}
