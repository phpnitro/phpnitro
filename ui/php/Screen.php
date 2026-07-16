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

    /**
     * Runs the onXxx handler for $action, passing it the submitted form
     * values. The handler may return a path (string) to redirect to;
     * returning null redirects back to the current page.
     *
     * @param array<string, string> $data Submitted input values (by input name).
     */
    public function handle(string $action, array $data = []): ?string
    {
        $method = 'on' . ucfirst($action);

        if (!method_exists($this, $method)) {
            throw new \RuntimeException("Unknown action \"{$action}\" for screen " . static::class);
        }

        $redirect = $this->$method($data);
        $_SESSION[$this->sessionKey] = $this->state;

        return is_string($redirect) ? $redirect : null;
    }
}
