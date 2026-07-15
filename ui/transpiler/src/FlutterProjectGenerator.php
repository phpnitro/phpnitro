<?php

namespace Transpiler;

/**
 * Writes the generated Dart screen (stateless or stateful) into an existing
 * Flutter project's lib/main.dart (the Flutter project itself is created
 * separately via `flutter create`, not by this class).
 *
 * This class only assembles Dart source from already-rendered pieces — it
 * has no knowledge of the PHP AST; all PHP-to-Dart translation happens
 * upstream (DartEmitter / StatementEmitter).
 */
final class FlutterProjectGenerator
{
    /**
     * @param array<int, array{name: string, dartType: string, defaultDart: string}> $stateFields
     *        Only used when $screen->kind === 'stateful'.
     * @param array<string, string> $env Raw .env values, embedded at compile time as phpxEnv.
     */
    public function generate(
        ScreenDefinition $screen,
        array $stateFields,
        string $buildBodyDart,
        array $env,
        string $flutterProjectDir,
    ): void {
        $libDir = $flutterProjectDir . '/lib';
        if (!is_dir($libDir)) {
            throw new \RuntimeException("Flutter project not found at {$flutterProjectDir} (run `flutter create` first).");
        }

        $this->writeEnvFile($env, $libDir);

        $screenDart = $screen->kind === 'stateful'
            ? $this->buildStatefulScreen($screen->className, $stateFields, $buildBodyDart)
            : $this->buildStatelessScreen($screen->className, $buildBodyDart);

        $dart = $this->buildMainDart($screen->className, $screenDart);

        file_put_contents($libDir . '/main.dart', $dart);
    }

    /**
     * @param array<string, string> $env
     */
    private function writeEnvFile(array $env, string $libDir): void
    {
        $lines = [];
        foreach ($env as $key => $value) {
            $lines[] = '  ' . var_export($key, true) . ': ' . var_export($value, true) . ',';
        }

        $body = implode("\n", $lines);

        file_put_contents($libDir . '/env.g.dart', <<<DART
        const Map<String, String> phpxEnv = {
        {$body}
        };

        DART);
    }

    private function buildMainDart(string $className, string $screenDart): string
    {
        return <<<DART
        import 'package:flutter/material.dart';
        import 'env.g.dart';

        void main() {
          runApp(const MyApp());
        }

        class MyApp extends StatelessWidget {
          const MyApp({super.key});

          @override
          Widget build(BuildContext context) {
            return MaterialApp(
              home: Scaffold(
                body: Center(
                  child: const {$className}(),
                ),
              ),
            );
          }
        }

        {$screenDart}
        DART;
    }

    private function buildStatelessScreen(string $className, string $buildBodyDart): string
    {
        return <<<DART
        class {$className} extends StatelessWidget {
          const {$className}({super.key});

          @override
          Widget build(BuildContext context) {
            return {$buildBodyDart};
          }
        }

        DART;
    }

    /**
     * @param array<int, array{name: string, dartType: string, defaultDart: string}> $stateFields
     */
    private function buildStatefulScreen(string $className, array $stateFields, string $buildBodyDart): string
    {
        $stateClassName = "_{$className}State";

        $fieldLines = '';
        foreach ($stateFields as $field) {
            $fieldLines .= "  {$field['dartType']} {$field['name']} = {$field['defaultDart']};\n";
        }

        return <<<DART
        class {$className} extends StatefulWidget {
          const {$className}({super.key});

          @override
          State<{$className}> createState() => {$stateClassName}();
        }

        class {$stateClassName} extends State<{$className}> {
        {$fieldLines}
          @override
          Widget build(BuildContext context) {
            return {$buildBodyDart};
          }
        }

        DART;
    }
}
