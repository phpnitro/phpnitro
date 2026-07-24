<?php

namespace Engine\Tests;

use Engine\AnimatedText;
use Engine\AutoSizeText;
use Engine\Button;
use Engine\Canvas;
use Engine\Checkbox;
use Engine\Color;
use Engine\FloatingActionButton;
use Engine\InfiniteScrollList;
use Engine\LottieView;
use Engine\ProgressBar;
use Engine\SwitchToggle;
use Engine\Text;
use Engine\Theme;
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

    public function testLottieViewRendersDataAttributes(): void
    {
        $html = LottieView::make('/assets/animations/success.json', loop: false, autoplay: false)->render();

        $this->assertStringContainsString('data-lottie-view', $html);
        $this->assertStringContainsString('data-src="/assets/animations/success.json"', $html);
        $this->assertStringContainsString('data-loop="0"', $html);
        $this->assertStringContainsString('data-autoplay="0"', $html);
    }

    public function testCanvasRendersEncodedOps(): void
    {
        $html = Canvas::make(200, 100)
            ->rect(0, 0, 50, 50, '#f00')
            ->circle(10, 10, 5, '#0f0')
            ->line(0, 0, 10, 10, '#00f', 2)
            ->text(5, 5, 'Hi', '#000')
            ->render();

        $this->assertStringContainsString('data-phpx-canvas', $html);
        $this->assertStringContainsString('width="200"', $html);
        $this->assertStringContainsString('height="100"', $html);
        $this->assertStringContainsString('&quot;type&quot;:&quot;rect&quot;', $html);
        $this->assertStringContainsString('&quot;type&quot;:&quot;circle&quot;', $html);
        $this->assertStringContainsString('&quot;type&quot;:&quot;line&quot;', $html);
        $this->assertStringContainsString('&quot;type&quot;:&quot;text&quot;', $html);
    }

    public function testCanvasEscapesTextContent(): void
    {
        $html = Canvas::make()->text(0, 0, '<script>alert(1)</script>')->render();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    public function testButtonWithTypedBackgroundReplacesDefaultClassesAndCouplesHover(): void
    {
        $html = Button::make('Go', background: Color::of('emerald', 600))->render();

        $this->assertStringContainsString('bg-emerald-600', $html);
        $this->assertStringContainsString('hover:bg-emerald-700', $html);
        $this->assertStringContainsString('text-white', $html);
        $this->assertStringNotContainsString('bg-blue-600', $html);
    }

    public function testButtonWithTypedBackgroundCapsHoverShadeAt900(): void
    {
        $html = Button::make('Go', background: Color::of('emerald', 900))->render();

        $this->assertStringContainsString('bg-emerald-900', $html);
        $this->assertStringContainsString('hover:bg-emerald-900', $html);
    }

    public function testButtonWithTypedForegroundOverridesDefaultWhiteText(): void
    {
        $html = Button::make('Go', background: Color::of('yellow', 300), foreground: Color::of('slate', 900))->render();

        $this->assertStringContainsString('text-slate-900', $html);
        $this->assertStringNotContainsString('text-white', $html);
    }

    protected function tearDown(): void
    {
        Theme::reset();
    }

    public function testThemeDefaultsMatchPreExistingHardcodedColors(): void
    {
        $this->assertSame('blue', Theme::primary()->name);
        $this->assertSame(600, Theme::primary()->shade);
    }

    public function testFloatingActionButtonFollowsThemePrimaryByDefault(): void
    {
        Theme::setPrimary(Color::of('emerald', 600));
        $html = FloatingActionButton::make('+')->render();

        $this->assertStringContainsString('bg-emerald-600', $html);
        $this->assertStringContainsString('hover:bg-emerald-700', $html);
    }

    public function testFloatingActionButtonExplicitBackgroundOverridesTheme(): void
    {
        Theme::setPrimary(Color::of('emerald', 600));
        $html = FloatingActionButton::make('+', background: Color::of('purple', 600))->render();

        $this->assertStringContainsString('bg-purple-600', $html);
        $this->assertStringNotContainsString('bg-emerald-600', $html);
    }

    public function testProgressBarFollowsThemePrimaryByDefault(): void
    {
        Theme::setPrimary(Color::of('emerald', 600));
        $html = ProgressBar::make(50)->render();

        $this->assertStringContainsString('bg-emerald-600', $html);
    }

    public function testCheckboxAccentFollowsThemePrimaryByDefault(): void
    {
        Theme::setPrimary(Color::of('emerald', 600));
        $html = Checkbox::make('opt', 'Option')->render();

        $this->assertStringContainsString('accent-emerald-600', $html);
    }

    public function testSwitchToggleActiveColorFollowsThemePrimaryByDefault(): void
    {
        Theme::setPrimary(Color::of('emerald', 600));
        $html = SwitchToggle::make('opt', 'Option')->render();

        $this->assertStringContainsString('peer-checked:bg-emerald-600', $html);
    }
}
