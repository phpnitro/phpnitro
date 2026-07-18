<?php

namespace Engine\Tests;

use Engine\BottomNavigation;
use Engine\Button;
use Engine\Checkbox;
use Engine\Color;
use Engine\Column;
use Engine\ErrorBanner;
use Engine\FontWeight;
use Engine\Form;
use Engine\Html;
use Engine\IconButton;
use Engine\ListView;
use Engine\Row;
use Engine\Scaffold;
use Engine\SelectBox;
use Engine\Stepper;
use Engine\Text;
use Engine\Textarea;
use Engine\TextField;
use Engine\TextSize;
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
            Checkbox::make('ok', 'OK', checked: true),
            SelectBox::make('lang', ['fr' => 'Français', 'en' => 'English'], selected: 'en'),
            Button::make('Envoyer'),
        ], action: 'save')->render();

        $this->assertStringContainsString('name="_action" value="save"', $html);
        $this->assertStringContainsString('name="email"', $html);
        $this->assertStringContainsString('checked', $html);
        $this->assertStringContainsString('<option value="en" selected>', $html);
        $this->assertStringContainsString('type="submit"', $html);
        $this->assertSame(1, substr_count($html, '<form'), 'inputs must share a single form');
    }

    public function testListViewWrapsEachChild(): void
    {
        $html = ListView::make([Text::make('a'), Text::make('b')])->render();

        $this->assertSame(2, substr_count($html, 'px-4 py-3'));
    }

    public function testStepperRendersLabelsAndHighlightsCurrentStep(): void
    {
        $html = Stepper::make(
            currentStep: 1,
            totalSteps: 3,
            stepLabels: ['Compte', 'Préférences', 'Résumé'],
            body: Text::make('body'),
            backAction: 'back',
            nextAction: 'next',
        )->render();

        $this->assertStringContainsString('Compte', $html);
        $this->assertStringContainsString('Préférences', $html);
        $this->assertStringContainsString('Résumé', $html);
        $this->assertSame(1, substr_count($html, '<form'), 'Stepper must own a single wrapping form');
        $this->assertStringContainsString('name="_action" value="back"', $html);
        $this->assertStringContainsString('name="_action" value="next"', $html);
    }

    public function testStepperOmitsBackButtonOnFirstStep(): void
    {
        $html = Stepper::make(
            currentStep: 0,
            totalSteps: 2,
            stepLabels: ['A', 'B'],
            body: Text::make('body'),
            backAction: null,
            nextAction: 'next',
        )->render();

        $this->assertStringNotContainsString('value="back"', $html);
        $this->assertStringContainsString('name="_action" value="next"', $html);
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

    public function testTextareaWithErrorAddsRedBorderAndMessage(): void
    {
        $html = Textarea::make('note', label: 'Note', error: 'Trop long')->render();

        $this->assertStringContainsString('border-red-500', $html);
        $this->assertStringContainsString('Trop long', $html);
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

    public function testIconButtonSupportsOnClick(): void
    {
        $html = IconButton::make('<svg></svg>', onClick: 'doStuff()')->render();

        $this->assertStringContainsString('onclick="doStuff()"', $html);
        $this->assertStringNotContainsString('<form', $html);
    }

    public function testScaffoldReservesBottomPaddingOnlyWhenHasBottomNav(): void
    {
        $with = Scaffold::make(body: Text::make('x'), hasBottomNav: true)->render();
        $without = Scaffold::make(body: Text::make('x'), hasBottomNav: false)->render();

        $this->assertStringContainsString('pb-24', $with);
        $this->assertStringContainsString('pb-4', $without);
        $this->assertStringNotContainsString('pb-24', $without);
    }

    public function testScaffoldNeverRendersItsOwnNav(): void
    {
        $html = Scaffold::make(body: Text::make('x'), hasBottomNav: true)->render();

        $this->assertStringNotContainsString('<nav', $html);
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
}
