<?php

namespace Engine\Tests;

use Engine\Button;
use Engine\Checkbox;
use Engine\Color;
use Engine\Column;
use Engine\FontWeight;
use Engine\Form;
use Engine\ListView;
use Engine\Row;
use Engine\SelectBox;
use Engine\Stepper;
use Engine\Text;
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
}
