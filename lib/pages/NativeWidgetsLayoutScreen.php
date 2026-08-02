<?php

namespace Engine\App;

use Engine\Color;
use Engine\Native\Alignment;
use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\AppBar;
use Engine\Native\Button;
use Engine\Native\Divider;
use Engine\Native\PageView;
use Engine\Native\Scaffold;
use Engine\Native\Table;
use Engine\Native\Align;
use Engine\Native\Animated;
use Engine\Native\Center;
use Engine\Native\Container;
use Engine\Native\CustomPaint;
use Engine\Native\Flex;
use Engine\Native\Hero;
use Engine\Native\Widget;
use Engine\Native\Padding;
use Engine\Native\Positioned;
use Engine\Native\Stack;
use Engine\Native\Tappable;
use Engine\Native\Text;
use Engine\Native\Wrap;
use Engine\Native\Tokens;

/**
 * The native conversion of WidgetsLayoutPage.php's static half — Align,
 * Center, Container, Row, Margin/Padding, Table, Stack/Positioned, Wrap,
 * Button, Canvas, PageView, Hero all have a real native equivalent by now
 * (see packages/ui/src/Native/'s widget layer). Full parity reached.
 *
 * FadeIn isn't a separate widget here because NativeCanvasView.kt already
 * does something structurally equivalent for every screen: setCommands()
 * crossfades the previous draw-command list into the new one
 * (fadeProgress/previousCommands) on every re-render — any element that's
 * new or removed already eases in/out, the same effect FadeIn exists for,
 * just applied screen-wide instead of per-element.
 *
 * Hero (Hero) and AnimatedContainer (Animated) are the SAME
 * primitive at the Kotlin level: both tag a subtree by key
 * (Canvas::beginHero()/endHero(), heroRegions in the JSON payload)
 * and NativeCanvasView.kt's drawHeroTransition() flies that subtree from
 * its old rect to its new one via a Matrix, interpolating each command's
 * own geometry/color fields too (not just the outer rect) — so a
 * background color or radius change eases smoothly, not just position and
 * size. Hero is for a cross-screen navigation; Animated is
 * the same mechanism used locally, for "this element looks different now,
 * ease into it" — Flutter's AnimatedContainer/AnimatedPositioned/
 * AnimatedOpacity unified into one implicit-animation primitive.
 */
final class NativeWidgetsLayoutScreen
{
    public static function build(float $screenWidth, float $screenHeight): Widget
    {
        $contentWidth = $screenWidth - 2 * Tokens::SPACE_XL;
        $layoutPage = (int) ($_GET['layout_page'] ?? '0');
        $heroOpen = ($_GET['hero_open'] ?? '') === '1';
        $animatedOpen = ($_GET['animated_open'] ?? '') === '1';

        $caption = static fn (string $text): Widget => new Padding(
            EdgeInsets::only(top: Tokens::SPACE_LG, bottom: Tokens::SPACE_SM),
            new Text($text, Tokens::TEXT_CAPTION, Tokens::inkMuted()->toHex(), bold: true, letterSpacing: 0.04),
        );

        $tag = static fn (string $label, ?Color $background = null): Widget => new Container(
            new Padding(EdgeInsets::symmetric(horizontal: Tokens::SPACE_MD, vertical: Tokens::SPACE_SM), new Text($label, Tokens::TEXT_BODY_SMALL, Color::white()->toHex())),
            background: $background ?? Tokens::ink(),
            radius: Tokens::RADIUS_SM,
        );

        $body = new Container(
            new Padding(
                EdgeInsets::all(Tokens::SPACE_XL),
                Flex::column([
                    $caption('Align — un enfant positionné dans une zone plus grande'),
                    new Container(new Align(new Text('coin', Tokens::TEXT_BODY, Color::white()->toHex()), Alignment::BOTTOM_RIGHT), width: $contentWidth, height: 96, background: Color::blue(600), radius: Tokens::RADIUS_MD),

                    $caption('Center — centre un seul enfant'),
                    new Container(new Center(new Text('centré', Tokens::TEXT_BODY, Color::white()->toHex())), width: $contentWidth, height: 96, background: Color::blue(600), radius: Tokens::RADIUS_MD),

                    $caption('Container — padding + fond + coins arrondis'),
                    new Container(new Padding(EdgeInsets::all(Tokens::SPACE_LG), new Text('Contenu', Tokens::TEXT_BODY, Tokens::ink()->toHex())), width: $contentWidth, background: Tokens::surfaceMuted(), radius: Tokens::RADIUS_LG),

                    $caption('Row — enfants alignés horizontalement'),
                    Flex::row([$tag('1'), new Padding(EdgeInsets::only(left: Tokens::SPACE_SM), $tag('2')), new Padding(EdgeInsets::only(left: Tokens::SPACE_SM), $tag('3'))]),

                    $caption('Margin / Padding — espacement autour ou dedans'),
                    new Container(new Padding(EdgeInsets::all(Tokens::SPACE_MD), $tag('espacé')), width: $contentWidth, background: Tokens::surfaceMuted(), radius: Tokens::RADIUS_LG),

                    $caption('Table'),
                    new Table(
                        rows: [['Casque sans fil', '89,90 €'], ['Montre connectée', '149,00 €']],
                        headers: ['Produit', 'Prix'],
                    ),

                    $caption('Stack / Positioned — superposition libre (badge en incrustation)'),
                    new Stack([
                        new Container(width: $contentWidth, height: 96, background: Color::blue(600), radius: Tokens::RADIUS_MD),
                        new Positioned(
                            new Container(new Padding(EdgeInsets::symmetric(horizontal: 6, vertical: 2), new Text('3', Tokens::TEXT_CAPTION, Color::white()->toHex(), bold: true)), background: Color::red(600), radius: Tokens::RADIUS_PILL),
                            top: 8.0,
                            right: 8.0,
                        ),
                    ]),

                    $caption('Wrap — enfants qui passent à la ligne au lieu de déborder'),
                    new Wrap([
                        $tag('tag-un', Color::of('purple', 600)),
                        $tag('tag-deux', Color::of('purple', 600)),
                        $tag('tag-trois', Color::of('purple', 600)),
                        $tag('tag-quatre', Color::of('purple', 600)),
                        $tag('tag-cinq', Color::of('purple', 600)),
                    ]),

                    $caption('Button — background/foreground typés'),
                    Flex::row([
                        new Button('Emerald', 'noop', background: Color::of('emerald', 600)),
                        new Padding(EdgeInsets::only(left: Tokens::SPACE_MD), new Button('Jaune', 'noop', background: Color::of('yellow', 300), foreground: Color::slate(900))),
                    ]),

                    $caption('Canvas — rect/circle/line/text, dessin unique au montage'),
                    CustomPaint::make(200, 100)
                        ->rect(0, 0, 60, 100, '#2563eb')
                        ->circle(100, 50, 30, '#16a34a')
                        ->line(140, 10, 190, 90, '#dc2626', 3)
                        ->text(10, 95, 'Canvas', '#111827'),
                    $caption('PageView — pagination réelle par tap (dots + chevrons)'),
                    new PageView([
                        new Container(new Center(new Text('Page A', Tokens::TEXT_BODY, Color::white()->toHex())), background: Color::blue(600), radius: Tokens::RADIUS_MD),
                        new Container(new Center(new Text('Page B', Tokens::TEXT_BODY, Color::white()->toHex())), background: Color::green(600), radius: Tokens::RADIUS_MD),
                        new Container(new Center(new Text('Page C', Tokens::TEXT_BODY, Color::white()->toHex())), background: Color::of('purple', 600), radius: Tokens::RADIUS_MD),
                    ], $layoutPage, 'layout_page'),

                    new Divider(),
                    $caption('Hero — FLIP réel par élément (tape pour agrandir)'),
                    $heroOpen
                        ? new Tappable(
                            new Hero(
                                new Container(new Center(new Text('hero', Tokens::TEXT_TITLE, Color::white()->toHex(), bold: true)), width: $contentWidth, height: 160, background: Color::of('purple', 600), radius: Tokens::RADIUS_LG),
                                'layout-hero-demo',
                            ),
                            'toggle:hero_open',
                            ['next' => '0'],
                        )
                        : new Tappable(
                            new Hero(
                                new Container(new Center(new Text('hero', Tokens::TEXT_BODY_SMALL, Color::white()->toHex(), bold: true)), width: 72, height: 40, background: Color::of('purple', 600), radius: Tokens::RADIUS_SM),
                                'layout-hero-demo',
                            ),
                            'toggle:hero_open',
                            ['next' => '1'],
                        ),

                    $caption('AnimatedContainer — taille + couleur + rayon, tous animés (tape pour agrandir)'),
                    $animatedOpen
                        ? new Tappable(
                            new Animated(
                                new Container(width: $contentWidth, height: 140, background: Color::of('emerald', 600), radius: Tokens::RADIUS_LG),
                                'layout-animated-demo',
                            ),
                            'toggle:animated_open',
                            ['next' => '0'],
                        )
                        : new Tappable(
                            new Animated(
                                new Container(width: 96, height: 48, background: Color::of('orange', 600), radius: Tokens::RADIUS_SM),
                                'layout-animated-demo',
                            ),
                            'toggle:animated_open',
                            ['next' => '1'],
                        ),
                ], crossAxisAlignment: CrossAxisAlignment::STRETCH),
            ),
            width: $screenWidth,
            background: Tokens::surfaceMuted(),
        );

        return new Scaffold(
            $body,
            $screenWidth,
            $screenHeight,
            appBar: new AppBar($screenWidth, 'Mise en page', backAction: 'back'),
        );
    }
}
