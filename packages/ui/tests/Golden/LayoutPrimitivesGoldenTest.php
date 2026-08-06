<?php

namespace Engine\Tests\Golden;

use Engine\Color;
use Engine\Native\Button;
use Engine\Native\Container;
use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\Flex;
use Engine\Native\Icon;
use Engine\Native\Padding;
use Engine\Native\Text;
use Engine\Native\Tokens;

/**
 * Small, focused fixtures — each exercises one layout concern in
 * isolation (padding insets, flex distribution, text wrapping) so a
 * mismatch points straight at what broke, instead of one giant screen
 * fixture where any of a hundred widgets could be the culprit. See
 * ScreensGoldenTest for the broader, whole-screen regression net.
 */
final class LayoutPrimitivesGoldenTest extends GoldenTestCase
{
    public function testContainerWithPadding(): void
    {
        $this->assertMatchesGolden(
            'container_with_padding',
            new Container(
                new Padding(EdgeInsets::all(16.0), new Text('Bonjour', 16.0, '#111827')),
                width: 200.0,
                height: 80.0,
                background: Color::gray(100),
                radius: 12.0,
            ),
        );
    }

    public function testFlexRowDistributesSpace(): void
    {
        $this->assertMatchesGolden(
            'flex_row_distribution',
            new Container(
                Flex::row([
                    new Container(width: 40.0, height: 40.0, background: Color::red(500)),
                    new Padding(EdgeInsets::only(left: 8.0), new Container(width: 40.0, height: 40.0, background: Color::blue(500))),
                ], crossAxisAlignment: CrossAxisAlignment::CENTER),
                width: 300.0,
                height: 60.0,
            ),
        );
    }

    public function testTextWrapsAtBoxWidth(): void
    {
        $this->assertMatchesGolden(
            'text_wrapping',
            new Container(
                new Text('Ce texte est assez long pour se répartir sur plusieurs lignes dans une boîte étroite.', 14.0, '#111827'),
                width: 160.0,
            ),
        );
    }

    public function testButtonWithIcon(): void
    {
        $this->assertMatchesGolden(
            'button_with_icon',
            new Button('Valider', 'submit:demo', icon: 'check', width: 200.0),
        );
    }

    public function testIconFontAwesome(): void
    {
        $this->assertMatchesGolden(
            'icon_fontawesome',
            new Icon('heart', 24.0, Tokens::danger()->toHex(), font: 'fontawesome'),
        );
    }
}
