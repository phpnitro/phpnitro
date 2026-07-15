<?php

namespace Transpiler;

/**
 * Injects the dependencies declared in phpx.json into a Flutter project's
 * pubspec.yaml, inside a clearly delimited block so repeated runs are
 * idempotent (no duplication, no stale entries left behind).
 */
final class PubspecDependencyWriter
{
    private const BEGIN_MARKER = '  # phpx:dependencies:begin';
    private const END_MARKER = '  # phpx:dependencies:end';

    /**
     * @param array<string, string> $dependencies package name => version constraint
     */
    public function write(array $dependencies, string $pubspecPath): void
    {
        if (!is_file($pubspecPath)) {
            throw new \RuntimeException("pubspec.yaml not found at {$pubspecPath}.");
        }

        $lines = file($pubspecPath, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new \RuntimeException("Unable to read {$pubspecPath}.");
        }

        $lines = $this->stripExistingBlock($lines);

        if ($dependencies === []) {
            file_put_contents($pubspecPath, implode("\n", $lines) . "\n");

            return;
        }

        $insertAt = $this->findDependenciesInsertionPoint($lines);

        $block = [self::BEGIN_MARKER];
        foreach ($dependencies as $name => $version) {
            $block[] = "  {$name}: {$version}";
        }
        $block[] = self::END_MARKER;

        array_splice($lines, $insertAt, 0, $block);

        file_put_contents($pubspecPath, implode("\n", $lines) . "\n");
    }

    /**
     * @param string[] $lines
     * @return string[]
     */
    private function stripExistingBlock(array $lines): array
    {
        $beginIndex = array_search(self::BEGIN_MARKER, $lines, true);
        $endIndex = array_search(self::END_MARKER, $lines, true);

        if ($beginIndex === false || $endIndex === false || $endIndex < $beginIndex) {
            return $lines;
        }

        array_splice($lines, $beginIndex, $endIndex - $beginIndex + 1);

        return $lines;
    }

    /**
     * @param string[] $lines
     */
    private function findDependenciesInsertionPoint(array $lines): int
    {
        $inDependencies = false;

        foreach ($lines as $index => $line) {
            if (trim($line) === 'dependencies:') {
                $inDependencies = true;
                continue;
            }

            if ($inDependencies && preg_match('/^\S/', $line) === 1) {
                return $index;
            }
        }

        if ($inDependencies) {
            return count($lines);
        }

        throw new \RuntimeException("No 'dependencies:' section found in pubspec.yaml.");
    }
}
