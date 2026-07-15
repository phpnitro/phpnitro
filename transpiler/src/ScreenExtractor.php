<?php

namespace Transpiler;

use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt;

final class StateField
{
    public function __construct(
        public readonly string $name,
        public readonly string $dartType,
        public readonly Expr $default,
    ) {
    }
}

final class ScreenDefinition
{
    /**
     * @param StateField[] $stateFields
     */
    public function __construct(
        public readonly string $className,
        public readonly string $kind,
        public readonly array $stateFields,
        public readonly Expr $buildReturnExpr,
    ) {
    }
}

/**
 * Locates the user's screen class (extending StatelessWidget or
 * StatefulWidget), extracts its state fields (if any) and the expression
 * returned by its build() method — everything the code generator needs.
 */
final class ScreenExtractor
{
    private const TYPE_MAP = [
        'int' => 'int',
        'float' => 'double',
        'string' => 'String',
        'bool' => 'bool',
    ];

    /**
     * @param Stmt[] $statements
     */
    public function extract(array $statements): ScreenDefinition
    {
        $class = $this->findScreenClass($statements);
        if ($class === null) {
            throw new \RuntimeException('No class extending StatelessWidget or StatefulWidget found.');
        }

        $kind = $class->extends !== null && $class->extends->getLast() === 'StatefulWidget'
            ? 'stateful'
            : 'stateless';

        $stateFields = $kind === 'stateful' ? $this->extractStateFields($class) : [];

        $build = $this->findMethod($class, 'build');
        if ($build === null) {
            throw new \RuntimeException("Class {$class->name} has no build() method.");
        }

        if ($build->stmts === null) {
            throw new \RuntimeException('build() method has no body.');
        }

        $returnExpr = null;
        foreach ($build->stmts as $stmt) {
            if ($stmt instanceof Stmt\Return_ && $stmt->expr !== null) {
                $returnExpr = $stmt->expr;
                break;
            }
        }

        if ($returnExpr === null) {
            throw new \RuntimeException('build() method has no return statement.');
        }

        return new ScreenDefinition($class->name->toString(), $kind, $stateFields, $returnExpr);
    }

    /**
     * @param Stmt[] $statements
     */
    private function findScreenClass(array $statements): ?Stmt\Class_
    {
        foreach ($statements as $stmt) {
            if ($stmt instanceof Stmt\Namespace_) {
                $found = $this->findScreenClass($stmt->stmts);
                if ($found !== null) {
                    return $found;
                }
            }

            if ($stmt instanceof Stmt\Class_
                && $stmt->extends !== null
                && in_array($stmt->extends->getLast(), ['StatelessWidget', 'StatefulWidget'], true)
            ) {
                return $stmt;
            }
        }

        return null;
    }

    private function findMethod(Stmt\Class_ $class, string $name): ?Stmt\ClassMethod
    {
        foreach ($class->stmts as $stmt) {
            if ($stmt instanceof Stmt\ClassMethod && $stmt->name->toString() === $name) {
                return $stmt;
            }
        }

        return null;
    }

    /**
     * @return StateField[]
     */
    private function extractStateFields(Stmt\Class_ $class): array
    {
        $fields = [];

        foreach ($class->stmts as $stmt) {
            if (!$stmt instanceof Stmt\Property) {
                continue;
            }

            if (!$stmt->type instanceof Identifier || !isset(self::TYPE_MAP[$stmt->type->toString()])) {
                throw new \RuntimeException('State properties must be declared with one of: int, float, string, bool.');
            }

            $dartType = self::TYPE_MAP[$stmt->type->toString()];

            foreach ($stmt->props as $prop) {
                if ($prop->default === null) {
                    throw new \RuntimeException("State property \${$prop->name->toString()} must have a default value.");
                }

                $fields[] = new StateField($prop->name->toString(), $dartType, $prop->default);
            }
        }

        return $fields;
    }
}
