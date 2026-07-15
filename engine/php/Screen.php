<?php

namespace Engine;

abstract class Screen
{
    protected array $state;

    private readonly string $sessionKey;

    /**
     * @param array<string, string> $params Route parameters extracted by Router (e.g. {id} in /product/{id}).
     */
    public function __construct(protected readonly array $params = [])
    {
        $this->sessionKey = static::class . ':' . implode(',', $params);
        $this->state = $_SESSION[$this->sessionKey] ?? $this->initialState();
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
        $_SESSION[$this->sessionKey] = $this->state;
    }
}
