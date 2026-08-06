<?php

namespace Engine\Tests;

use Engine\Native\Constraints;
use Engine\Native\Canvas;
use Engine\Native\RichText;
use Engine\Native\TextSpan;
use PHPUnit\Framework\TestCase;

final class RichTextTest extends TestCase
{
    /** @return array<int, array<string, mixed>> */
    private function paintToCommands(RichText $node, float $maxWidth = 1000.0): array
    {
        $node->layout(new Constraints(0, $maxWidth, 0, Constraints::INFINITY));
        $canvas = new Canvas();
        $node->paint($canvas, 0, 0);

        return json_decode($canvas->toJson(), true)['commands'] ?? [];
    }

    public function testEachWordBecomesItsOwnTextCommand(): void
    {
        $commands = $this->paintToCommands(new RichText([
            new TextSpan('Bonjour le monde'),
        ]));

        $words = array_map(static fn (array $c): string => $c['text'], $commands);
        $this->assertSame(['Bonjour', 'le', 'monde'], $words);
    }

    public function testSpanStyleAppliesOnlyToItsOwnWords(): void
    {
        $commands = $this->paintToCommands(new RichText([
            new TextSpan('Normal '),
            new TextSpan('gras', bold: true, color: '#FF0000'),
        ]));

        $normal = $commands[0];
        $bold = $commands[1];

        $this->assertSame('Normal', $normal['text']);
        $this->assertArrayNotHasKey('bold', $normal);
        $this->assertSame('#000000', $normal['color']);

        $this->assertSame('gras', $bold['text']);
        $this->assertTrue($bold['bold']);
        $this->assertSame('#FF0000', $bold['color']);
    }

    public function testAdjacentSpansWrapTogetherAsOneParagraph(): void
    {
        // A narrow width forces a wrap — if spans wrapped independently
        // instead of as one token stream, "monde" would never move to a
        // second line no matter how narrow the box is.
        $commands = $this->paintToCommands(new RichText([
            new TextSpan('Un mot '),
            new TextSpan('important et un autre monde'),
        ]), maxWidth: 60.0);

        $ys = array_unique(array_map(static fn (array $c): float => $c['y'], $commands));
        $this->assertGreaterThan(1, count($ys), 'expected the paragraph to wrap onto more than one line');
    }

    public function testActionSpanRegistersATappableHitRegion(): void
    {
        $node = new RichText([
            new TextSpan('Lire les '),
            new TextSpan('conditions', action: 'navigate:terms'),
        ]);
        $node->layout(new Constraints(0, 1000, 0, Constraints::INFINITY));
        $canvas = new Canvas();
        $node->paint($canvas, 0, 0);
        $payload = json_decode($canvas->toJson(), true);

        $this->assertCount(1, $payload['hitRegions']);
        $this->assertSame('navigate:terms', $payload['hitRegions'][0]['action']);
    }

    public function testLayoutReportsHeightForMultipleLines(): void
    {
        $one = new RichText([new TextSpan('court')]);
        $twoLines = new RichText([new TextSpan('un texte assez long pour forcer deux lignes ici')]);

        $sizeOne = $one->layout(new Constraints(0, 1000, 0, Constraints::INFINITY));
        $sizeTwo = $twoLines->layout(new Constraints(0, 60, 0, Constraints::INFINITY));

        $this->assertGreaterThan($sizeOne->height, $sizeTwo->height);
    }
}
