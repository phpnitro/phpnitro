<?php

namespace Transpiler;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar;

/**
 * Recursively translates a PHP widget-tree expression (nested Sdk widget
 * static calls, plus the small expression subset allowed inside setState
 * closures) into the equivalent Dart source code. Any construct outside the
 * supported subset raises an explicit error rather than guessing.
 */
final class DartEmitter
{
    /**
     * @param string[] $stateFieldNames Names of the screen's declared state
     *                                  fields, used to validate $this->x reads.
     */
    public function __construct(private readonly array $stateFieldNames = [])
    {
    }

    public function emit(Expr $node): string
    {
        if ($node instanceof Expr\StaticCall) {
            return $this->emitStaticCall($node);
        }

        if ($node instanceof Expr\Array_) {
            return $this->emitArray($node);
        }

        if ($node instanceof Scalar\String_) {
            return var_export($node->value, true);
        }

        if ($node instanceof Scalar\LNumber || $node instanceof Scalar\DNumber) {
            return var_export($node->value, true);
        }

        if ($node instanceof Expr\PropertyFetch) {
            return $this->emitPropertyFetch($node);
        }

        if ($node instanceof Expr\BinaryOp\Concat) {
            $left = $this->emit($node->left);
            $right = $this->emit($node->right);

            return "'\${" . $left . "}\${" . $right . "}'";
        }

        if ($node instanceof Expr\BinaryOp\Plus) {
            return "({$this->emit($node->left)} + {$this->emit($node->right)})";
        }

        if ($node instanceof Expr\BinaryOp\Minus) {
            return "({$this->emit($node->left)} - {$this->emit($node->right)})";
        }

        if ($node instanceof Expr\BinaryOp\Mul) {
            return "({$this->emit($node->left)} * {$this->emit($node->right)})";
        }

        if ($node instanceof Expr\BinaryOp\Div) {
            return "({$this->emit($node->left)} / {$this->emit($node->right)})";
        }

        throw new \RuntimeException('Unsupported PHP expression: ' . get_class($node));
    }

    private function emitPropertyFetch(Expr\PropertyFetch $node): string
    {
        if (!$node->var instanceof Expr\Variable || $node->var->name !== 'this') {
            throw new \RuntimeException('Only $this->property reads are supported.');
        }

        if (!$node->name instanceof Identifier) {
            throw new \RuntimeException('Unsupported dynamic property access.');
        }

        $name = $node->name->toString();

        if (!in_array($name, $this->stateFieldNames, true)) {
            throw new \RuntimeException("Unknown state property: \${$name}");
        }

        return $name;
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

        $positional = [];
        $named = [];
        foreach ($node->args as $arg) {
            if (!$arg instanceof Arg) {
                throw new \RuntimeException("Unsupported argument spread in {$phpClass}::new().");
            }

            if ($arg->name !== null) {
                $named[$arg->name->toString()] = $arg->value;
            } else {
                $positional[] = $arg->value;
            }
        }

        if ($widget['argMode'] === 'button') {
            return $this->emitButton($positional, $named);
        }

        if (count($positional) !== 1 || $named !== []) {
            throw new \RuntimeException("{$phpClass}::new() must be called with exactly one positional argument.");
        }

        $argCode = $this->emit($positional[0]);

        return match ($widget['argMode']) {
            'positional' => "{$widget['dart']}({$argCode})",
            'named' => "{$widget['dart']}({$widget['argName']}: {$argCode})",
            default => throw new \RuntimeException("Unknown argMode '{$widget['argMode']}' for widget {$phpClass}."),
        };
    }

    /**
     * @param Expr[] $positional
     * @param array<string, Expr> $named
     */
    private function emitButton(array $positional, array $named): string
    {
        if (count($positional) !== 1) {
            throw new \RuntimeException('Button::new() requires exactly one positional label argument.');
        }

        $label = $this->emit($positional[0]);
        $onPressed = 'null';

        if (isset($named['onPressed'])) {
            if (!$named['onPressed'] instanceof Expr\Closure) {
                throw new \RuntimeException('Button onPressed must be an inline closure with no parameters.');
            }

            $onPressed = $this->emitOnPressedClosure($named['onPressed']);
        }

        return "ElevatedButton(onPressed: {$onPressed}, child: Text({$label}))";
    }

    private function emitOnPressedClosure(Expr\Closure $closure): string
    {
        if ($closure->params !== []) {
            throw new \RuntimeException('onPressed closures must take no parameters.');
        }

        $body = (new StatementEmitter($this))->emitBlock($closure->stmts);

        return "() {\n{$body}\n}";
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
}
