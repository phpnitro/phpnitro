<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine\Tests\Golden;

use Engine\Native\Canvas;
use Engine\Native\Constraints;
use Engine\Native\Widget;
use PHPUnit\Framework\TestCase;

/**
 * Catches SILENT regressions in the layout/paint pipeline itself — every
 * other test in this project (Camera::captureAction() etc.) checks that
 * a widget produces the RIGHT action string, but nothing checks that the
 * actual pixel-level output of layout()+paint() (positions, sizes, every
 * rect/text/icon command) stays the same when nobody touches that
 * widget's code. A refactor of Flex's flex-distribution math, an
 * off-by-one in Padding, a Tokens constant nudged by one pixel — none of
 * these fail a single existing test, but this would.
 *
 * A "golden" test lays a widget out, paints it, and diffs the resulting
 * Canvas::toJson() against a checked-in fixture byte-for-byte.
 * renderTimeMs and the stable hash are stripped before comparing (the
 * hash is DERIVED from the rest of the payload, and renderTimeMs is
 * real wall-clock time — neither is meaningful to freeze).
 *
 * Updating a fixture after an INTENTIONAL layout change:
 *   GOLDEN_UPDATE=1 vendor/bin/phpunit --testsuite ui --filter Golden
 * Review the resulting fixture diff like any other code change — a
 * golden test only tells you something changed, not whether the change
 * was correct.
 */
abstract class GoldenTestCase extends TestCase
{
    protected function assertMatchesGolden(string $name, Widget $widget, float $width = 400.0, float $height = 800.0): void
    {
        $widget->layout(Constraints::loose($width, $height));
        $canvas = new Canvas();
        $widget->paint($canvas, 0.0, 0.0);

        $actual = $this->normalize($canvas->toJson());
        $fixturePath = $this->fixturePath($name);

        if (getenv('GOLDEN_UPDATE') === '1') {
            if (!is_dir(dirname($fixturePath))) {
                mkdir(dirname($fixturePath), 0777, true);
            }
            file_put_contents($fixturePath, $actual . "\n");
            $this->markTestSkipped("Golden fixture '{$name}' written — re-run without GOLDEN_UPDATE to verify.");
        }

        if (!is_file($fixturePath)) {
            $this->fail(
                "No golden fixture for '{$name}' yet. Create it with:\n" .
                "GOLDEN_UPDATE=1 vendor/bin/phpunit --testsuite ui --filter Golden\n" .
                "then review {$fixturePath} before committing it.",
            );
        }

        $expected = rtrim(file_get_contents($fixturePath), "\n");

        $this->assertSame(
            $expected,
            $actual,
            "Golden mismatch for '{$name}' — the layout/paint output changed. " .
            "If this is an intentional layout change, regenerate with:\n" .
            "GOLDEN_UPDATE=1 vendor/bin/phpunit --testsuite ui --filter Golden",
        );
    }

    private function normalize(string $json): string
    {
        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        unset($decoded['renderTimeMs'], $decoded['hash']);

        return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function fixturePath(string $name): string
    {
        return __DIR__ . "/__fixtures__/{$name}.json";
    }
}
