<?php

namespace Transpiler;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar;

/**
 * Recursively translates a PHP widget-tree expression (nested Sdk widget
 * static calls) into the equivalent Dart source code. Any construct outside
 * the supported subset raises an explicit error rather than guessing.
 */
final class DartEmitter
{
    public function emit(Expr $node): string
    {
        if ($node instanceof Expr\StaticCall) {
            return $this->emitStaticCall($node);
        }

        if ($node instanceof Expr\Array_) {
            return $this->emitArray($node);
        }

        if ($node instanceof Scalar\String_) {
            return $this->emitString($node);
        }

        throw new \RuntimeException('Unsupported PHP expression in widget tree: ' . get_class($node));
    }

    private function emitStaticCall(Expr\StaticCall $node): string
    {
        if (!$node->class instanceof Name) {
            throw new \RuntimeException('Unsupported dynamic class reference in static call.');
        }

        $phpClass = $node->class->getLast();

        if (!$node->name instanceof Identifier || $node->name->toString() !== 'new') {
            $methodName = $node->name instanceof Identifier ? $node->name->toString() : '(dynamic)';
            throw new \RuntimeException("Unsupported static method call: {$phpClass}::{$methodName}(); only ::new() is supported.");
        }

        $widget = WidgetMap::get($phpClass);

        if (count($node->args) !== 1 || !$node->args[0] instanceof Arg) {
            throw new \RuntimeException("{$phpClass}::new() must be called with exactly one argument.");
        }

        $argCode = $this->emit($node->args[0]->value);

        return match ($widget['argMode']) {
            'positional' => "{$widget['dart']}({$argCode})",
            'named' => "{$widget['dart']}({$widget['argName']}: {$argCode})",
            'button' => "ElevatedButton(onPressed: null, child: Text({$argCode}))",
            default => throw new \RuntimeException("Unknown argMode '{$widget['argMode']}' for widget {$phpClass}."),
        };
    }

    private function emitArray(Expr\Array_ $node): string
    {
        $items = [];
        foreach ($node->items as $item) {
            if ($item === null || $item->value === null) {
                throw new \RuntimeException('Unsupported array item (spread or null) in widget tree.');
            }

            $items[] = $this->emit($item->value);
        }

        return '[' . implode(', ', $items) . ']';
    }

    private function emitString(Scalar\String_ $node): string
    {
        return var_export($node->value, true);
    }
}
