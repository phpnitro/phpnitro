<?php

namespace Engine\Tests;

use Engine\BottomNavigation;
use Engine\Button;
use Engine\Color;
use Engine\Column;
use Engine\Container;
use Engine\Curves;
use Engine\ErrorBanner;
use Engine\FadeIn;
use Engine\FontWeight;
use Engine\Form;
use Engine\Html;
use Engine\Positioned;
use Engine\Rounded;
use Engine\Row;
use Engine\SelectBox;
use Engine\Stack;
use Engine\Text;
use Engine\TextField;
use Engine\TextSize;
use Engine\Wrap;
use PHPUnit\Framework\TestCase;

final class WidgetsTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testTextEscapesHtml(): void
    {
        $this->assertStringContainsString('&lt;script&gt;', Text::make('<script>')->render());
    }

    public function testTypedStyleOverridesRawClasses(): void
    {
        $html = Text::make('x', size: TextSize::XL2, weight: FontWeight::BOLD, color: Color::gray(900))->render();

        $this->assertStringContainsString('class="text-2xl font-bold text-gray-900"', $html);
    }

    public function testColumnAndRowNestChildren(): void
    {
        $html = Column::make([Row::make([Text::make('a')]), Text::make('b')])->render();

        $this->assertStringContainsString('flex-col', $html);
        $this->assertStringContainsString('flex-row', $html);
        $this->assertStringContainsString('>a<', $html);
    }

    public function testButtonWithActionRendersCsrfProtectedForm(): void
    {
        $html = Button::make('Go', action: 'go')->render();

        $this->assertStringContainsString('name="_action" value="go"', $html);
        $this->assertStringContainsString('name="_token"', $html);
    }

    public function testFormBundlesInputsUnderOneAction(): void
    {
        $html = Form::make([
            TextField::make('email', label: 'Email'),
            SelectBox::make('lang', ['fr' => 'Français', 'en' => 'English'], selected: 'en'),
            Button::make('Envoyer'),
        ], action: 'save')->render();

        $this->assertStringContainsString('name="_action" value="save"', $html);
        $this->assertStringContainsString('name="email"', $html);
        $this->assertStringContainsString('<option value="en" selected>', $html);
        $this->assertStringContainsString('type="submit"', $html);
        $this->assertSame(1, substr_count($html, '<form'), 'inputs must share a single form');
    }

    public function testErrorBannerRendersNothingWhenMessageIsNull(): void
    {
        $this->assertSame('', ErrorBanner::make(null)->render());
    }

    public function testErrorBannerRendersMessageEscaped(): void
    {
        $html = ErrorBanner::make('<b>bad</b> input')->render();

        $this->assertStringContainsString('&lt;b&gt;bad&lt;/b&gt; input', $html);
        $this->assertStringContainsString('bg-red-50', $html);
    }

    public function testTextFieldWithoutErrorIsUnchanged(): void
    {
        $html = TextField::make('email', label: 'Email')->render();

        $this->assertStringNotContainsString('border-red-500', $html);
        $this->assertStringNotContainsString('text-red-600', $html);
    }

    public function testTextFieldWithErrorAddsRedBorderAndMessage(): void
    {
        $html = TextField::make('email', label: 'Email', error: 'Email invalide')->render();

        $this->assertStringContainsString('border-red-500', $html);
        $this->assertStringContainsString('Email invalide', $html);
    }

    public function testSelectBoxWithErrorAddsRedBorderAndMessage(): void
    {
        $html = SelectBox::make('lang', ['fr' => 'Français'], label: 'Langue', error: 'Choix requis')->render();

        $this->assertStringContainsString('border-red-500', $html);
        $this->assertStringContainsString('Choix requis', $html);
    }

    public function testHtmlRawPassesThroughUnescaped(): void
    {
        $this->assertSame('<script>hi</script>', Html::raw('<script>hi</script>')->render());
    }

    public function testButtonOnClickTakesPriorityOverAction(): void
    {
        $html = Button::make('Vibrer', action: 'shouldBeIgnored', onClick: 'phpxDevice.vibrate(200)')->render();

        $this->assertStringContainsString('onclick="phpxDevice.vibrate(200)"', $html);
        $this->assertStringNotContainsString('<form', $html);
        $this->assertStringNotContainsString('shouldBeIgnored', $html);
    }

    public function testBottomNavigationRendersStableIdAndActiveInactiveClassData(): void
    {
        $_SERVER['REQUEST_URI'] = '/settings';

        $html = BottomNavigation::make([
            ['label' => 'Accueil', 'href' => '/'],
            ['label' => 'Réglages', 'href' => '/settings'],
        ])->render();

        $this->assertStringContainsString('id="phpx-bottom-nav"', $html);
        $this->assertStringContainsString('data-active-class="', $html);
        $this->assertStringContainsString('data-inactive-class="', $html);

        // The currently active tab's rendered class should match its own active-class data attribute.
        preg_match('/<a href="\/settings" class="([^"]+)" data-active-class="([^"]+)"/', $html, $matches);
        $this->assertSame($matches[2], $matches[1]);
    }

    public function testFadeInRendersChildWrappedWithAnimationCustomProperties(): void
    {
        $html = FadeIn::make(Text::make('salut'), durationMs: 250, delayMs: 50, curve: Curves::EASE_IN_OUT, distancePx: 8)->render();

        $this->assertStringContainsString('class="phpx-animate"', $html);
        $this->assertStringContainsString('--phpx-duration:250ms;', $html);
        $this->assertStringContainsString('--phpx-delay:50ms;', $html);
        $this->assertStringContainsString('--phpx-curve:ease-in-out;', $html);
        $this->assertStringContainsString('--phpx-distance:8px;', $html);
        $this->assertStringContainsString('>salut<', $html);
    }

    public function testFadeInEscapesCurveValue(): void
    {
        $html = FadeIn::make(Text::make('x'), curve: '"><script>alert(1)</script>')->render();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    public function testContainerTypedStyleIsAddedOnTopOfRawClasses(): void
    {
        $html = Container::make(Text::make('x'), 'h-24 flex', background: Color::blue(600), rounded: Rounded::XL)->render();

        $this->assertStringContainsString('class="h-24 flex bg-blue-600 rounded-xl"', $html);
    }

    public function testContainerWithoutTypedStyleKeepsRawClassesOnly(): void
    {
        $html = Container::make(Text::make('x'))->render();

        $this->assertStringContainsString('class="p-4"', $html);
    }

    public function testStackFillsPlainChildrenAndPositionsPositionedOnes(): void
    {
        $html = Stack::make([
            Text::make('fond'),
            Positioned::make(Text::make('badge'), top: 4, right: 8),
        ])->render();

        $this->assertStringContainsString('class="relative"', $html);
        $this->assertStringContainsString('<div class="absolute inset-0"><p', $html);
        $this->assertStringContainsString('<div class="absolute" style="top:4px;right:8px;">', $html);
    }

    public function testPositionedOmitsUnsetOffsets(): void
    {
        $html = Positioned::make(Text::make('x'), left: 10)->render();

        $this->assertStringContainsString('style="left:10px;"', $html);
    }

    public function testWrapRendersFlexWrapChildren(): void
    {
        $html = Wrap::make([Text::make('a'), Text::make('b')])->render();

        $this->assertStringContainsString('flex-wrap', $html);
        $this->assertStringContainsString('>a<', $html);
        $this->assertStringContainsString('>b<', $html);
    }

}
