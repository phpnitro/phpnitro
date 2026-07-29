<?php

namespace Engine\App;

use Engine\Color;
use Engine\Native\Alignment;
use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\NativeAppBar;
use Engine\Native\NativeButton;
use Engine\Native\NativeDivider;
use Engine\Native\NativePageView;
use Engine\Native\NativeScaffold;
use Engine\Native\NativeTable;
use Engine\Native\RenderAlign;
use Engine\Native\RenderCenter;
use Engine\Native\RenderContainer;
use Engine\Native\RenderCustomPaint;
use Engine\Native\RenderFlex;
use Engine\Native\RenderNode;
use Engine\Native\RenderPadding;
use Engine\Native\RenderPositioned;
use Engine\Native\RenderStack;
use Engine\Native\RenderText;
use Engine\Native\RenderWrap;
use Engine\Native\Tokens;

/**
 * The native conversion of WidgetsLayoutPage.php's static half — Align,
 * Center, Container, Row, Margin/Padding, Table, Stack/Positioned, Wrap,
 * Button, Canvas, PageView all have a real native equivalent by now (see
 * packages/ui/src/Native/'s widget layer).
 *
 * FadeIn/AnimatedContainer aren't separate widgets here because
 * NativeCanvasView.kt already does something structurally equivalent for
 * every screen: setCommands() crossfades the previous draw-command list
 * into the new one (fadeProgress/previousCommands) on every re-render —
 * so any element that's new, resized, or recolored between two requests
 * already eases into place, the same "ease into what changed" effect
 * those two widgets exist for, just applied screen-wide instead of
 * per-element.
 *
 * Hero (a specific tagged element FLIP-animating its own position/size
 * between two screens, independently of the rest of the transition) is
 * the one genuine gap — that needs per-element tracking across a
 * navigation, not just a whole-screen crossfade, real additional work
 * this doesn't attempt. Stays WebView-only.
 */
final class NativeWidgetsLayoutScreen
{
    public static function build(float $screenWidth, float $screenHeight): RenderNode
    {
        $contentWidth = $screenWidth - 2 * Tokens::SPACE_XL;
        $layoutPage = (int) ($_GET['layout_page'] ?? '0');

        $caption = static fn (string $text): RenderNode => new RenderPadding(
            EdgeInsets::only(top: Tokens::SPACE_LG, bottom: Tokens::SPACE_SM),
            new RenderText($text, Tokens::TEXT_CAPTION, Tokens::inkMuted()->toHex(), bold: true, letterSpacing: 0.04),
        );

        $tag = static fn (string $label, ?Color $background = null): RenderNode => new RenderContainer(
            new RenderPadding(EdgeInsets::symmetric(horizontal: Tokens::SPACE_MD, vertical: Tokens::SPACE_SM), new RenderText($label, Tokens::TEXT_BODY_SMALL, Color::white()->toHex())),
            background: $background ?? Tokens::ink(),
            radius: Tokens::RADIUS_SM,
        );

        $body = new RenderContainer(
            new RenderPadding(
                EdgeInsets::all(Tokens::SPACE_XL),
                RenderFlex::column([
                    $caption('Align — un enfant positionné dans une zone plus grande'),
                    new RenderContainer(new RenderAlign(new RenderText('coin', Tokens::TEXT_BODY, Color::white()->toHex()), Alignment::BOTTOM_RIGHT), width: $contentWidth, height: 96, background: Color::blue(600), radius: Tokens::RADIUS_MD),

                    $caption('Center — centre un seul enfant'),
                    new RenderContainer(new RenderCenter(new RenderText('centré', Tokens::TEXT_BODY, Color::white()->toHex())), width: $contentWidth, height: 96, background: Color::blue(600), radius: Tokens::RADIUS_MD),

                    $caption('Container — padding + fond + coins arrondis'),
                    new RenderContainer(new RenderPadding(EdgeInsets::all(Tokens::SPACE_LG), new RenderText('Contenu', Tokens::TEXT_BODY, Tokens::ink()->toHex())), width: $contentWidth, background: Tokens::surfaceMuted(), radius: Tokens::RADIUS_LG),

                    $caption('Row — enfants alignés horizontalement'),
                    RenderFlex::row([$tag('1'), new RenderPadding(EdgeInsets::only(left: Tokens::SPACE_SM), $tag('2')), new RenderPadding(EdgeInsets::only(left: Tokens::SPACE_SM), $tag('3'))]),

                    $caption('Margin / Padding — espacement autour ou dedans'),
                    new RenderContainer(new RenderPadding(EdgeInsets::all(Tokens::SPACE_MD), $tag('espacé')), width: $contentWidth, background: Tokens::surfaceMuted(), radius: Tokens::RADIUS_LG),

                    $caption('Table'),
                    new NativeTable(
                        rows: [['Casque sans fil', '89,90 €'], ['Montre connectée', '149,00 €']],
                        headers: ['Produit', 'Prix'],
                    ),

                    $caption('Stack / Positioned — superposition libre (badge en incrustation)'),
                    new RenderStack([
                        new RenderContainer(width: $contentWidth, height: 96, background: Color::blue(600), radius: Tokens::RADIUS_MD),
                        new RenderPositioned(
                            new RenderContainer(new RenderPadding(EdgeInsets::symmetric(horizontal: 6, vertical: 2), new RenderText('3', Tokens::TEXT_CAPTION, Color::white()->toHex(), bold: true)), background: Color::red(600), radius: Tokens::RADIUS_PILL),
                            top: 8.0,
                            right: 8.0,
                        ),
                    ]),

                    $caption('Wrap — enfants qui passent à la ligne au lieu de déborder'),
                    new RenderWrap([
                        $tag('tag-un', Color::of('purple', 600)),
                        $tag('tag-deux', Color::of('purple', 600)),
                        $tag('tag-trois', Color::of('purple', 600)),
                        $tag('tag-quatre', Color::of('purple', 600)),
                        $tag('tag-cinq', Color::of('purple', 600)),
                    ]),

                    $caption('Button — background/foreground typés'),
                    RenderFlex::row([
                        new NativeButton('Emerald', 'noop', background: Color::of('emerald', 600)),
                        new RenderPadding(EdgeInsets::only(left: Tokens::SPACE_MD), new NativeButton('Jaune', 'noop', background: Color::of('yellow', 300), foreground: Color::slate(900))),
                    ]),

                    $caption('Canvas — rect/circle/line/text, dessin unique au montage'),
                    RenderCustomPaint::make(200, 100)
                        ->rect(0, 0, 60, 100, '#2563eb')
                        ->circle(100, 50, 30, '#16a34a')
                        ->line(140, 10, 190, 90, '#dc2626', 3)
                        ->text(10, 95, 'Canvas', '#111827'),
                    $caption('PageView — pagination réelle par tap (dots + chevrons)'),
                    new NativePageView([
                        new RenderContainer(new RenderCenter(new RenderText('Page A', Tokens::TEXT_BODY, Color::white()->toHex())), background: Color::blue(600), radius: Tokens::RADIUS_MD),
                        new RenderContainer(new RenderCenter(new RenderText('Page B', Tokens::TEXT_BODY, Color::white()->toHex())), background: Color::green(600), radius: Tokens::RADIUS_MD),
                        new RenderContainer(new RenderCenter(new RenderText('Page C', Tokens::TEXT_BODY, Color::white()->toHex())), background: Color::of('purple', 600), radius: Tokens::RADIUS_MD),
                    ], $layoutPage, 'layout_page'),

                    new NativeDivider(),
                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_LG),
                        new RenderText('Hero (FLIP par élément entre deux écrans) reste sur le pipeline WebView.', Tokens::TEXT_BODY_SMALL, Tokens::inkMuted()->toHex()),
                    ),
                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        new NativeButton('Voir sur WebView', 'webview:/widgets/layout', background: Tokens::surfaceMuted(), foreground: Tokens::ink()),
                    ),
                ], crossAxisAlignment: CrossAxisAlignment::STRETCH),
            ),
            width: $screenWidth,
            background: Tokens::surfaceMuted(),
        );

        return new NativeScaffold(
            $body,
            $screenWidth,
            $screenHeight,
            appBar: new NativeAppBar($screenWidth, 'Mise en page', backAction: 'back'),
        );
    }
}
