<?php

namespace Transpiler;

/**
 * Maps PHP SDK widget class names (Sdk\Text, Sdk\Column, ...) to their
 * Dart/Flutter equivalent and the argument convention used to translate
 * the single PHP constructor argument into the Dart constructor call.
 */
final class WidgetMap
{
    private const MAP = [
        'Text' => ['dart' => 'Text', 'argMode' => 'positional'],
        'Container' => ['dart' => 'Container', 'argMode' => 'named', 'argName' => 'child'],
        'Column' => ['dart' => 'Column', 'argMode' => 'named', 'argName' => 'children'],
        'Row' => ['dart' => 'Row', 'argMode' => 'named', 'argName' => 'children'],
        'Button' => ['dart' => 'ElevatedButton', 'argMode' => 'button'],
    ];

    public static function has(string $phpClass): bool
    {
        return isset(self::MAP[$phpClass]);
    }

    /**
     * @return array{dart: string, argMode: string, argName?: string}
     */
    public static function get(string $phpClass): array
    {
        if (!self::has($phpClass)) {
            throw new \RuntimeException("Unsupported widget: {$phpClass}");
        }

        return self::MAP[$phpClass];
    }
}
