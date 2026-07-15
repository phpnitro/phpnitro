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
     * @return array{class: class-string<Screen>, params: array<string, string>}
     */
    public function resolve(string $path): array
    {
        $pathSegments = $this->segments($path);

        foreach ($this->routes as $pattern => $class) {
            $patternSegments = $this->segments($pattern);

            if (count($patternSegments) !== count($pathSegments)) {
                continue;
            }

            $params = [];
            $matches = true;

            foreach ($patternSegments as $i => $segment) {
                if (str_starts_with($segment, '{') && str_ends_with($segment, '}')) {
                    $params[substr($segment, 1, -1)] = $pathSegments[$i];
                    continue;
                }

                if ($segment !== $pathSegments[$i]) {
                    $matches = false;
                    break;
                }
            }

            if ($matches) {
                return ['class' => $class, 'params' => $params];
            }
        }

        throw new \RuntimeException("No route registered for path: {$path}");
    }

    /**
     * @return string[]
     */
    private function segments(string $path): array
    {
        $trimmed = trim($path, '/');

        return $trimmed === '' ? [] : explode('/', $trimmed);
    }
}
