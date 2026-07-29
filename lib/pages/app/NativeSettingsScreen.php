<?php

namespace Engine\App;

use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\Flexible;
use Engine\Native\NativeIconCircle;
use Engine\Native\NativeListTile;
use Engine\Native\RenderContainer;
use Engine\Native\RenderFlex;
use Engine\Native\RenderImage;
use Engine\Native\RenderNode;
use Engine\Native\RenderPadding;
use Engine\Native\RenderText;
use Engine\Native\Tokens;
use Engine\Preferences\Preferences;

/**
 * Point 5 of the "grow the framework" pass: not another synthetic mockup
 * screen — this one reads and displays the SAME Engine\Preferences\
 * values SettingsPage.php (the real, WebView-rendered settings screen)
 * reads and writes, proving the native pipeline can serve a genuinely
 * data-backed screen, not just a static reference-image recreation.
 * Reachable by tapping the gear icon on NativeOtpScreen.
 *
 * Built from the NativeIconCircle/NativeListTile widget layer instead of
 * hand-rolled RenderContainer/RenderCenter/RenderIcon composition —
 * compare to git history before this refactor if you want to see what
 * that looked like duplicated across three screens.
 */
final class NativeSettingsScreen
{
    public static function build(float $screenWidth): RenderNode
    {
        $accent = Preferences::get('accent_color', 'blue');
        $nativePreviewEnabled = Preferences::get('native_render_preview_enabled', '0') === '1';

        return new RenderContainer(
            new RenderPadding(
                EdgeInsets::all(Tokens::SPACE_XL),
                RenderFlex::column([
                    new NativeIconCircle('arrow_back', action: 'back'),
                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_LG),
                        RenderFlex::row([
                            new RenderImage(
                                'https://picsum.photos/200',
                                64,
                                64,
                                radius: 32,
                            ),
                            new Flexible(new RenderPadding(EdgeInsets::only(left: Tokens::SPACE_MD), RenderFlex::column([
                                new RenderText('Réglages natifs', Tokens::TEXT_TITLE, Tokens::ink()->toHex(), bold: true),
                                new RenderPadding(
                                    EdgeInsets::only(top: 2),
                                    new RenderText('Données réelles — Engine\\Preferences\\', Tokens::TEXT_CAPTION, Tokens::inkMuted()->toHex()),
                                ),
                            ]))),
                        ], crossAxisAlignment: CrossAxisAlignment::CENTER),
                    ),
                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_XL),
                        new NativeListTile("Couleur d'accent", null, 'palette', leadingColor: Tokens::inkSecondary(), trailingText: $accent),
                    ),
                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        new NativeListTile('Rendu natif', null, 'bolt', leadingColor: Tokens::inkSecondary(), trailingText: $nativePreviewEnabled ? 'Activé' : 'Désactivé'),
                    ),
                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        new NativeListTile('Moteur', null, 'memory', leadingColor: Tokens::inkSecondary(), trailingText: 'PHP -> Canvas'),
                    ),
                ], crossAxisAlignment: CrossAxisAlignment::STRETCH),
            ),
            width: $screenWidth,
            background: Tokens::surfaceMuted(),
        );
    }
}
