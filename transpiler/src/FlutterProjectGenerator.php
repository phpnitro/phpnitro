<?php

namespace Transpiler;

/**
 * Writes the generated Dart widget tree into an existing Flutter project's
 * lib/main.dart (the Flutter project itself is created separately via
 * `flutter create`, not by this class).
 */
final class FlutterProjectGenerator
{
    public function generate(string $widgetTreeDart, string $flutterProjectDir): void
    {
        $libDir = $flutterProjectDir . '/lib';
        if (!is_dir($libDir)) {
            throw new \RuntimeException("Flutter project not found at {$flutterProjectDir} (run `flutter create` first).");
        }

        file_put_contents($libDir . '/main.dart', $this->buildMainDart($widgetTreeDart));
    }

    private function buildMainDart(string $widgetTreeDart): string
    {
        return <<<DART
        import 'package:flutter/material.dart';

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
                  child: {$widgetTreeDart},
                ),
              ),
            );
          }
        }

        DART;
    }
}
