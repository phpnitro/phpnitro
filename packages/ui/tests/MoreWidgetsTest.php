<?php

namespace Engine\Tests;

use Engine\Align;
use Engine\Alignment;
use Engine\AnimatedContainer;
use Engine\AppBar;
use Engine\AudioPlayer;
use Engine\Center;
use Engine\CircularProgress;
use Engine\Color;
use Engine\DatePicker;
use Engine\Divider;
use Engine\Flash;
use Engine\FlashMessage;
use Engine\FutureBuilder;
use Engine\GoogleTranslate;
use Engine\Hero;
use Engine\IconButton;
use Engine\Image;
use Engine\Link;
use Engine\LinkWrap;
use Engine\LocationButton;
use Engine\Margin;
use Engine\Padding;
use Engine\PageView;
use Engine\ProgressBar;
use Engine\Rounded;
use Engine\SingleScrollView;
use Engine\Stepper;
use Engine\StreamBuilder;
use Engine\Table;
use Engine\Text;
use Engine\Theme;
use Engine\ThemeToggle;
use Engine\TimePicker;
use Engine\VideoPlayer;
use Engine\Widget;
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

    public function testCircularProgressRendersSvgWithComputedDashoffset(): void
    {
        $html = CircularProgress::make(50, size: 100)->render();

        $this->assertStringContainsString('width="100"', $html);
        $this->assertStringContainsString('aria-valuenow="50"', $html);
        $this->assertStringContainsString('role="progressbar"', $html);
    }

    public function testCircularProgressClampsOutOfRangeValue(): void
    {
        $html = CircularProgress::make(150)->render();

        $this->assertStringContainsString('aria-valuenow="100"', $html);
    }

    public function testDividerRendersHrWithClasses(): void
    {
        $html = Divider::make('my-custom-class')->render();

        $this->assertSame('<hr class="my-custom-class">', $html);
    }

    public function testFlashMessageRendersNothingWithoutPendingFlash(): void
    {
        unset($_SESSION['_flash']);
        $html = FlashMessage::make()->render();

        $this->assertSame('', $html);
    }

    public function testFlashMessageConsumesAndRendersPendingFlash(): void
    {
        Flash::set('Ajouté au panier', 'success');
        $html = FlashMessage::make()->render();

        $this->assertStringContainsString('Ajouté au panier', $html);
        $this->assertStringContainsString('bg-green-600', $html);
        $this->assertArrayNotHasKey('_flash', $_SESSION);
    }

    public function testFlashMessageErrorTypeUsesRedBackground(): void
    {
        Flash::set('Erreur de connexion', 'error');
        $html = FlashMessage::make()->render();

        $this->assertStringContainsString('bg-red-600', $html);
    }

    public function testImageRendersSrcAltAndClasses(): void
    {
        $html = Image::make('/photo.jpg', 'Une photo', 'rounded-lg')->render();

        $this->assertStringContainsString('src="/photo.jpg"', $html);
        $this->assertStringContainsString('alt="Une photo"', $html);
        $this->assertStringContainsString('class="rounded-lg"', $html);
    }

    public function testImageNetworkIsAnAliasForMake(): void
    {
        $html = Image::network('https://example.com/photo.jpg')->render();

        $this->assertStringContainsString('src="https://example.com/photo.jpg"', $html);
    }

    public function testLinkRendersAnchorWithEscapedHref(): void
    {
        $html = Link::make('Accueil', '/?a=1&b=2')->render();

        $this->assertStringContainsString('href="/?a=1&amp;b=2"', $html);
        $this->assertStringContainsString('>Accueil<', $html);
    }

    public function testLinkWrapWrapsAnyWidgetInAnAnchor(): void
    {
        $html = LinkWrap::make(Text::make('Carte produit'), '/product/42')->render();

        $this->assertStringContainsString('<a href="/product/42"', $html);
        $this->assertStringContainsString('>Carte produit<', $html);
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

    public function testThemeToggleRendersAPostFormWithCsrfField(): void
    {
        $html = ThemeToggle::make()->render();

        $this->assertStringContainsString('name="_action" value="toggleTheme"', $html);
        $this->assertStringContainsString('<form method="post"', $html);
    }

    public function testLocationButtonRendersButtonAndOutputSpanWithMatchingId(): void
    {
        $html = LocationButton::make('Ma position')->render();

        $this->assertStringContainsString('>Ma position<', $html);
        preg_match('/phpxDevice\.locate\(\'([a-z0-9_]+)\'\)/', $html, $m);
        $this->assertNotEmpty($m);
        $this->assertStringContainsString('id="' . $m[1] . '"', $html);
    }

    public function testDatePickerRendersInputWithMinMax(): void
    {
        $html = DatePicker::make('birthdate', 'Date de naissance', min: '2000-01-01', max: '2020-01-01')->render();

        $this->assertStringContainsString('type="date"', $html);
        $this->assertStringContainsString('name="birthdate"', $html);
        $this->assertStringContainsString('min="2000-01-01"', $html);
        $this->assertStringContainsString('max="2020-01-01"', $html);
        $this->assertStringContainsString('Date de naissance', $html);
    }

    public function testDatePickerWithoutLabelOmitsWrappingLabel(): void
    {
        $html = DatePicker::make('birthdate')->render();

        $this->assertStringNotContainsString('<label', $html);
    }

    public function testTimePickerRendersInputWithValue(): void
    {
        $html = TimePicker::make('slot', 'Créneau', '14:30')->render();

        $this->assertStringContainsString('type="time"', $html);
        $this->assertStringContainsString('value="14:30"', $html);
        $this->assertStringContainsString('Créneau', $html);
    }

    public function testAppBarRendersTitleAndBackLink(): void
    {
        $html = AppBar::make('Détail produit', backHref: '/products')->render();

        $this->assertStringContainsString('Détail produit', $html);
        $this->assertStringContainsString('href="/products"', $html);
        $this->assertStringContainsString('aria-label="Retour"', $html);
    }

    public function testAppBarWithoutBackHrefOrLeadingOmitsLeading(): void
    {
        $html = AppBar::make('Accueil')->render();

        $this->assertStringNotContainsString('aria-label="Retour"', $html);
    }

    public function testAppBarLeadingWidgetTakesPriorityOverBackHref(): void
    {
        $html = AppBar::make('Titre', backHref: '/back', leading: Text::make('menu'))->render();

        $this->assertStringContainsString('>menu<', $html);
        $this->assertStringNotContainsString('aria-label="Retour"', $html);
    }

    public function testAudioPlayerRendersSrcAndControlFlags(): void
    {
        $html = AudioPlayer::make('/audio/beep.mp3', autoplay: true, loop: true)->render();

        $this->assertStringContainsString('src="/audio/beep.mp3"', $html);
        $this->assertStringContainsString(' controls', $html);
        $this->assertStringContainsString(' autoplay', $html);
        $this->assertStringContainsString(' loop', $html);
    }

    public function testVideoPlayerRendersPosterAndMutedAutoplay(): void
    {
        $html = VideoPlayer::make('/video/demo.mp4', autoplay: true, poster: '/img/poster.jpg')->render();

        $this->assertStringContainsString('src="/video/demo.mp4"', $html);
        $this->assertStringContainsString('poster="/img/poster.jpg"', $html);
        // autoplay video must also be muted or Chromium's autoplay policy blocks it silently.
        $this->assertStringContainsString('autoplay muted', $html);
    }

    public function testGoogleTranslateEmbedsConfiguredLanguages(): void
    {
        $html = GoogleTranslate::make('en', 'en,fr')->render();

        $this->assertStringContainsString("pageLanguage: 'en'", $html);
        $this->assertStringContainsString("includedLanguages: 'en,fr'", $html);
        $this->assertStringContainsString('translate_a/element.js', $html);
    }

    public function testStreamBuilderRendersInitialWidgetAndPollingAttributes(): void
    {
        $html = StreamBuilder::make('/fragment/server-time', Text::make('Chargement...'), intervalMs: 5000)->render();

        $this->assertStringContainsString('data-stream-endpoint="/fragment/server-time"', $html);
        $this->assertStringContainsString('data-stream-interval="5000"', $html);
        $this->assertStringContainsString('Chargement...', $html);
    }

    public function testFutureBuilderRendersLoadingWidgetAndEndpoint(): void
    {
        $html = FutureBuilder::make('/fragment/once', Text::make('...'))->render();

        $this->assertStringContainsString('data-future-endpoint="/fragment/once"', $html);
        $this->assertStringContainsString('>...<', $html);
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

    public function testAppBarWithTypedBackground(): void
    {
        $html = AppBar::make('Titre', background: Color::of('emerald', 600))->render();

        $this->assertStringContainsString('bg-emerald-600', $html);
    }

    public function testLocationButtonWithTypedBackground(): void
    {
        $html = LocationButton::make(background: Color::of('emerald', 600))->render();

        $this->assertStringContainsString('bg-emerald-600', $html);
    }

    public function testIconButtonWithTypedForegroundAddsOnTopOfDefaultClasses(): void
    {
        $html = IconButton::make('★', foreground: Color::of('red', 600))->render();

        $this->assertStringContainsString('text-red-600', $html);
        $this->assertStringContainsString('rounded-full', $html);
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

    public function testProgressBarAcceptsATypedColorInsteadOfARawString(): void
    {
        $html = ProgressBar::make(50, barColor: Color::of('purple', 600))->render();

        $this->assertStringContainsString('bg-purple-600', $html);
    }

    public function testStepperWithTypedActiveColor(): void
    {
        $html = Stepper::make(
            currentStep: 1,
            totalSteps: 2,
            stepLabels: ['A', 'B'],
            body: Text::make('body'),
            nextAction: 'next',
            activeColor: Color::of('purple', 600),
        )->render();

        $this->assertStringContainsString('bg-purple-600', $html);
    }

    public function testStepperDefaultsToThemePrimary(): void
    {
        Theme::setPrimary(Color::of('emerald', 600));
        $html = Stepper::make(
            currentStep: 0,
            totalSteps: 2,
            stepLabels: ['A', 'B'],
            body: Text::make('body'),
        )->render();

        $this->assertStringContainsString('bg-emerald-600', $html);
    }
}
