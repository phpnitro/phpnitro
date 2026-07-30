<?php

namespace Engine\Tests;

use Engine\Align;
use Engine\Alignment;
use Engine\AnimatedContainer;
use Engine\Center;
use Engine\Color;
use Engine\Divider;
use Engine\Hero;
use Engine\Link;
use Engine\Margin;
use Engine\Padding;
use Engine\PageView;
use Engine\Rounded;
use Engine\SingleScrollView;
use Engine\Table;
use Engine\Text;
use Engine\Theme;
use PHPUnit\Framework\TestCase;

final class MoreWidgetsTest extends TestCase
{
    public function testAlignWrapsChildWithAlignmentClasses(): void
    {
        $html = Align::make(Text::make('coin'), Alignment::BOTTOM_RIGHT)->render();

        $this->assertStringContainsString('items-end justify-end', $html);
        $this->assertStringContainsString('>coin<', $html);
    }

    public function testCenterWrapsChild(): void
    {
        $html = Center::make(Text::make('centré'))->render();

        $this->assertStringContainsString('items-center justify-center', $html);
        $this->assertStringContainsString('>centré<', $html);
    }

    public function testDividerRendersHrWithClasses(): void
    {
        $html = Divider::make('my-custom-class')->render();

        $this->assertSame('<hr class="my-custom-class">', $html);
    }

    public function testLinkRendersAnchorWithEscapedHref(): void
    {
        $html = Link::make('Accueil', '/?a=1&b=2')->render();

        $this->assertStringContainsString('href="/?a=1&amp;b=2"', $html);
        $this->assertStringContainsString('>Accueil<', $html);
    }

    public function testMarginWrapsChildWithClasses(): void
    {
        $html = Margin::make(Text::make('x'), 'm-6')->render();

        $this->assertStringContainsString('class="m-6"', $html);
    }

    public function testPaddingWrapsChildWithClasses(): void
    {
        $html = Padding::make(Text::make('x'), 'px-6 py-2')->render();

        $this->assertStringContainsString('class="px-6 py-2"', $html);
    }

    public function testTableRendersHeadersAndRows(): void
    {
        $html = Table::make(
            rows: [['Casque', '89,90 €'], ['Montre', '149,00 €']],
            headers: ['Produit', 'Prix'],
        )->render();

        $this->assertStringContainsString('<th class="px-3 py-2 font-semibold">Produit</th>', $html);
        $this->assertStringContainsString('<td class="px-3 py-2">Casque</td>', $html);
        $this->assertStringContainsString('149,00 €', $html);
    }

    public function testTableWithoutHeadersOmitsThead(): void
    {
        $html = Table::make(rows: [['a', 'b']])->render();

        $this->assertStringNotContainsString('<thead>', $html);
    }

    public function testTableCellAcceptsWidgetNotJustString(): void
    {
        $html = Table::make(rows: [[Text::make('gras', 'font-bold'), 'b']])->render();

        $this->assertStringContainsString('font-bold', $html);
    }

    public function testPageViewWrapsEachPageInASnapContainer(): void
    {
        $html = PageView::make([Text::make('Page A'), Text::make('Page B')])->render();

        $this->assertStringContainsString('snap-x', $html);
        $this->assertStringContainsString('>Page A<', $html);
        $this->assertStringContainsString('>Page B<', $html);
    }

    public function testSingleScrollViewWrapsChild(): void
    {
        $html = SingleScrollView::make(Text::make('contenu'))->render();

        $this->assertStringContainsString('overflow-y-auto', $html);
        $this->assertStringContainsString('>contenu<', $html);
    }

    public function testAnimatedContainerRendersKeyDurationAndCurve(): void
    {
        $html = AnimatedContainer::make(Text::make('box'), key: 'card-1', durationMs: 250, curve: 'ease-out')->render();

        $this->assertStringContainsString('data-animated-container="card-1"', $html);
        $this->assertStringContainsString('data-duration="250"', $html);
        $this->assertStringContainsString('data-curve="ease-out"', $html);
        $this->assertStringContainsString('>box<', $html);
    }

    public function testAnimatedContainerAddsBackgroundAndRoundedOnTopOfClasses(): void
    {
        $html = AnimatedContainer::make(
            Text::make('box'),
            key: 'card-1',
            classes: 'h-24',
            background: Color::of('emerald', 600),
            rounded: Rounded::LG,
        )->render();

        $this->assertStringContainsString('h-24', $html);
        $this->assertStringContainsString('bg-emerald-600', $html);
        $this->assertStringContainsString('rounded-lg', $html);
    }

    public function testAnimatedContainerDefaultsToThreeHundredMsEaseInOut(): void
    {
        $html = AnimatedContainer::make(Text::make('box'), key: 'card-1')->render();

        $this->assertStringContainsString('data-duration="300"', $html);
        $this->assertStringContainsString('data-curve="ease-in-out"', $html);
    }

    public function testHeroRendersTagDurationAndCurve(): void
    {
        $html = Hero::make(Text::make('photo'), tag: 'product-42', durationMs: 500, curve: 'ease')->render();

        $this->assertStringContainsString('data-hero="product-42"', $html);
        $this->assertStringContainsString('data-duration="500"', $html);
        $this->assertStringContainsString('data-curve="ease"', $html);
        $this->assertStringContainsString('>photo<', $html);
    }

    public function testHeroEscapesTag(): void
    {
        $html = Hero::make(Text::make('photo'), tag: '"><script>alert(1)</script>')->render();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    protected function tearDown(): void
    {
        Theme::reset();
    }

    public function testDividerWithTypedColorReplacesDefaultBorderColor(): void
    {
        $html = Divider::make(color: Color::of('emerald', 600))->render();

        $this->assertStringContainsString('border-emerald-600', $html);
        $this->assertStringNotContainsString('border-gray-200', $html);
    }

    public function testDividerWithoutColorKeepsDefaultClasses(): void
    {
        $html = Divider::make()->render();

        $this->assertStringContainsString('border-gray-200', $html);
    }
}
