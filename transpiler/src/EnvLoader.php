<?php

namespace Transpiler;

/**
 * Parses a .env file (KEY=VALUE per line, '#' comments, blank lines ignored)
 * into a plain associative array. Values are embedded into the generated
 * Dart app at compile time — there is no runtime .env parsing on-device.
 */
final class EnvLoader
{
    /**
     * @return array<string, string>
     */
    public function load(string $envPath): array
    {
        if (!is_file($envPath)) {
            return [];
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            throw new \RuntimeException("Unable to read {$envPath}.");
        }

        $values = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                throw new \RuntimeException("Invalid .env line (expected KEY=VALUE): {$line}");
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
                $value = trim($value, $value[0]);
            }

            $values[$key] = $value;
        }

        return $values;
    }
}
