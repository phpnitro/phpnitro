<?php

namespace Transpiler;

use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

/**
 * Locates the user's widget class (a class extending StatelessWidget) and
 * extracts the expression returned by its build() method — the root of the
 * widget tree to translate to Dart.
 */
final class WidgetTreeExtractor
{
    /**
     * @param Stmt[] $statements
     */
    public function extract(array $statements): Expr
    {
        $class = $this->findWidgetClass($statements);
        if ($class === null) {
            throw new \RuntimeException('No class extending StatelessWidget found.');
        }

        $build = $this->findMethod($class, 'build');
        if ($build === null) {
            throw new \RuntimeException("Class {$class->name} has no build() method.");
        }

        if ($build->stmts === null) {
            throw new \RuntimeException('build() method has no body.');
        }

        foreach ($build->stmts as $stmt) {
            if ($stmt instanceof Stmt\Return_ && $stmt->expr !== null) {
                return $stmt->expr;
            }
        }

        throw new \RuntimeException('build() method has no return statement.');
    }

    /**
     * @param Stmt[] $statements
     */
    private function findWidgetClass(array $statements): ?Stmt\Class_
    {
        foreach ($statements as $stmt) {
            if ($stmt instanceof Stmt\Namespace_) {
                $found = $this->findWidgetClass($stmt->stmts);
                if ($found !== null) {
                    return $found;
                }
            }

            if ($stmt instanceof Stmt\Class_
                && $stmt->extends !== null
                && $stmt->extends->getLast() === 'StatelessWidget'
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
}
