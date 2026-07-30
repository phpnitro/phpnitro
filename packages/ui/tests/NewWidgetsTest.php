<?php

namespace Engine\Tests;

use Engine\Button;
use Engine\Canvas;
use Engine\Color;
use Engine\ProgressBar;
use Engine\Text;
use Engine\Theme;
use PHPUnit\Framework\TestCase;

final class NewWidgetsTest extends TestCase
{

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

    public function testProgressBarFollowsThemePrimaryByDefault(): void
    {
        Theme::setPrimary(Color::of('emerald', 600));
        $html = ProgressBar::make(50)->render();

        $this->assertStringContainsString('bg-emerald-600', $html);
    }

}
