<?php

namespace Engine\Tests;

use Engine\Icon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IconTest extends TestCase
{
    /**
     * Every icon must render as a single well-formed <svg> — this is what
     * actually catches a typo'd path/polygon/line attribute (a malformed
     * SVG silently renders nothing or garbles in a WebView), not just that
     * the PHP method runs.
     *
     * @return array<int, array{0: string}>
     */
    public static function iconNames(): array
    {
        return array_map(
            static fn (string $name) => [$name],
            [
                'home', 'settings', 'camera', 'bolt', 'rocket', 'link', 'hamburger', 'chevronDown', 'cart', 'user', 'warning',
                'check', 'close', 'search', 'heart', 'star', 'trash', 'edit', 'download', 'upload', 'share',
                'calendar', 'clock', 'mail', 'phone', 'lock', 'bell', 'plus', 'minus',
                'chevronLeft', 'chevronRight', 'chevronUp', 'arrowLeft', 'arrowRight', 'info', 'eye',
            ],
        );
    }

    #[DataProvider('iconNames')]
    public function testIconRendersWellFormedSvg(string $name): void
    {
        $svg = Icon::$name();

        $this->assertStringStartsWith('<svg xmlns="http://www.w3.org/2000/svg"', $svg);

        $doc = new \DOMDocument();
        $this->assertTrue(@$doc->loadXML($svg) !== false, "{$name}() did not produce well-formed XML");
    }

    public function testIconAcceptsCustomClasses(): void
    {
        $this->assertStringContainsString('class="w-8 h-8 text-red-600"', Icon::check('w-8 h-8 text-red-600'));
    }

    public function testIconEscapesClasses(): void
    {
        $this->assertStringNotContainsString('"><script>', Icon::check('"><script>alert(1)</script>'));
    }
}
