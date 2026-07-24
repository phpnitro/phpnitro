<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
     * Read-only view of this screen's current state — used by
     * PageRenderer's DevTools panel to show live state instead of just
     * route/timing. Not for app logic (that's what $this->state is for
     * inside the screen itself).
     */
    public function state(): array
    {
        return $this->state;
    }

    /**
     * Whether the persistent bottom nav (rendered once by PageRenderer,
     * see index.php) should be visible on this screen — override to
     * false for screens like login/checkout that don't want it at all.
     */
    public function showsBottomNav(): bool
    {
        return true;
    }

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
