<?php

namespace Transpiler;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt;

/**
 * Translates the small statement subset allowed inside a setState() closure
 * body: property assignment and nested setState() calls. Expressions are
 * delegated to the DartEmitter passed in at construction (so both share the
 * same known state-field names).
 */
final class StatementEmitter
{
    public function __construct(private readonly DartEmitter $exprEmitter)
    {
    }

    /**
     * @param Stmt[] $statements
     */
    public function emitBlock(array $statements): string
    {
        $lines = [];
        foreach ($statements as $stmt) {
            $lines[] = $this->emitStatement($stmt);
        }

        return implode("\n", $lines);
    }

    private function emitStatement(Stmt $stmt): string
    {
        if (!$stmt instanceof Stmt\Expression) {
            throw new \RuntimeException('Unsupported statement in closure body: ' . get_class($stmt));
        }

        $expr = $stmt->expr;

        if ($expr instanceof Expr\Assign) {
            return $this->emitPropertyAssign($expr) . ';';
        }

        if ($expr instanceof Expr\MethodCall) {
            return $this->emitSetStateCall($expr) . ';';
        }

        throw new \RuntimeException('Unsupported expression statement in closure body: ' . get_class($expr));
    }

    private function emitPropertyAssign(Expr\Assign $assign): string
    {
        if (!$assign->var instanceof Expr\PropertyFetch) {
            throw new \RuntimeException('Only $this->property = ... assignments are supported.');
        }

        $target = $this->exprEmitter->emit($assign->var);
        $value = $this->exprEmitter->emit($assign->expr);

        return "{$target} = {$value}";
    }

    private function emitSetStateCall(Expr\MethodCall $call): string
    {
        if (!$call->var instanceof Expr\Variable || $call->var->name !== 'this') {
            throw new \RuntimeException('Only $this->setState(...) calls are supported here.');
        }

        if (!$call->name instanceof Identifier || $call->name->toString() !== 'setState') {
            $name = $call->name instanceof Identifier ? $call->name->toString() : '(dynamic)';
            throw new \RuntimeException("Unsupported method call: \$this->{$name}(); only setState() is supported.");
        }

        if (count($call->args) !== 1
            || !$call->args[0] instanceof Arg
            || !$call->args[0]->value instanceof Expr\Closure
        ) {
            throw new \RuntimeException('setState() must be called with exactly one inline closure argument.');
        }

        $closure = $call->args[0]->value;

        if ($closure->params !== []) {
            throw new \RuntimeException('setState() closures must take no parameters.');
        }

        $body = $this->emitBlock($closure->stmts);

        return "setState(() {\n{$body}\n})";
    }
}
