<?php

namespace Engine\Tests;

use Engine\AnimatedText;
use Engine\AutoSizeText;
use Engine\InfiniteScrollList;
use Engine\Text;
use PHPUnit\Framework\TestCase;

final class NewWidgetsTest extends TestCase
{
    public function testAutoSizeTextRendersDataAttributes(): void
    {
        $html = AutoSizeText::make('Titre', minSize: 12, maxSize: 40)->render();

        $this->assertStringContainsString('data-autosize-text', $html);
        $this->assertStringContainsString('data-min-size="12"', $html);
        $this->assertStringContainsString('data-max-size="40"', $html);
        $this->assertStringContainsString('>Titre<', $html);
    }

    public function testAutoSizeTextEscapesContent(): void
    {
        $html = AutoSizeText::make('<script>alert(1)</script>')->render();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    public function testAnimatedTextRendersJsonEncodedTexts(): void
    {
        $html = AnimatedText::make(['Bonjour', 'Hello']);

        $this->assertStringContainsString('data-animated-text', $html->render());
        $this->assertStringContainsString('Bonjour', $html->render());
        $this->assertStringContainsString('Hello', $html->render());
    }

    public function testInfiniteScrollListRendersInitialItemsAndSentinel(): void
    {
        $html = InfiniteScrollList::make('/api/items', [Text::make('a'), Text::make('b')])->render();

        $this->assertStringContainsString('data-endpoint="/api/items"', $html);
        $this->assertStringContainsString('data-infinite-scroll-sentinel', $html);
        $this->assertStringContainsString('>a<', $html);
        $this->assertStringContainsString('>b<', $html);
    }
}
