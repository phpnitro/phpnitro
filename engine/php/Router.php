<?php

namespace Engine;

final class Router
{
    /**
     * @param array<string, class-string<Screen>> $routes
     */
    public function __construct(private readonly array $routes)
    {
    }

    /**
     * @return class-string<Screen>
     */
    public function resolve(string $path): string
    {
        $path = rtrim($path, '/');
        if ($path === '') {
            $path = '/';
        }

        if (!isset($this->routes[$path])) {
            throw new \RuntimeException("No route registered for path: {$path}");
        }

        return $this->routes[$path];
    }
}
