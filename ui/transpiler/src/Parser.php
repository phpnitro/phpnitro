<?php

namespace Transpiler;

use PhpParser\Error;
use PhpParser\Node\Stmt;
use PhpParser\ParserFactory;

final class Parser
{
    /**
     * @return Stmt[]
     */
    public function parseFile(string $path): array
    {
        $code = file_get_contents($path);
        if ($code === false) {
            throw new \RuntimeException("Unable to read file: {$path}");
        }

        $parser = (new ParserFactory())->createForNewestSupportedVersion();

        try {
            $statements = $parser->parse($code);
        } catch (Error $e) {
            throw new \RuntimeException("PHP parse error in {$path}: " . $e->getMessage(), 0, $e);
        }

        if ($statements === null) {
            throw new \RuntimeException("Unable to parse file: {$path}");
        }

        return $statements;
    }
}
