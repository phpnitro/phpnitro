<?php

namespace Engine\App;

use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\Flexible;
use Engine\Native\RenderCenter;
use Engine\Native\RenderContainer;
use Engine\Native\RenderFlex;
use Engine\Native\RenderIcon;
use Engine\Native\RenderImage;
use Engine\Native\RenderNode;
use Engine\Native\RenderPadding;
use Engine\Native\RenderTappable;
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
 */
final class NativeSettingsScreen
{
    public static function build(float $screenWidth): RenderNode
    {
        $accent = Preferences::get('accent_color', 'blue');
        $nativePreviewEnabled = Preferences::get('native_render_preview_enabled', '0') === '1';

        $backCircle = new RenderTappable(
            new RenderContainer(
                new RenderCenter(new RenderIcon('arrow_back', 20, Tokens::ink()->toHex())),
                width: 40,
                height: 40,
                radius: 20,
                background: Tokens::surfaceMuted(),
            ),
            action: 'back',
        );

        $row = static fn (string $label, string $value, string $icon): RenderContainer => new RenderContainer(
            new RenderPadding(
                EdgeInsets::symmetric(Tokens::SPACE_LG, Tokens::SPACE_MD),
                RenderFlex::row([
                    new RenderContainer(
                        new RenderCenter(new RenderIcon($icon, 18, Tokens::inkSecondary()->toHex())),
                        width: 36,
                        height: 36,
                        radius: 18,
                        background: Tokens::surfaceMuted(),
                    ),
                    new Flexible(new RenderPadding(
                        EdgeInsets::only(left: Tokens::SPACE_MD),
                        new RenderText($label, Tokens::TEXT_BODY, Tokens::ink()->toHex(), bold: true),
                    )),
                    new RenderText($value, Tokens::TEXT_BODY_SMALL, Tokens::inkSecondary()->toHex()),
                ], crossAxisAlignment: CrossAxisAlignment::CENTER),
            ),
            background: Tokens::surface(),
            radius: Tokens::RADIUS_LG,
            borderColor: Tokens::border(),
            borderWidth: 1.0,
        );

        return new RenderContainer(
            new RenderPadding(
                EdgeInsets::all(Tokens::SPACE_XL),
                RenderFlex::column([
                    $backCircle,
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
                    new RenderPadding(EdgeInsets::only(top: Tokens::SPACE_XL), $row("Couleur d'accent", $accent, 'palette')),
                    new RenderPadding(EdgeInsets::only(top: Tokens::SPACE_MD), $row('Rendu natif', $nativePreviewEnabled ? 'Activé' : 'Désactivé', 'bolt')),
                    new RenderPadding(EdgeInsets::only(top: Tokens::SPACE_MD), $row('Moteur', 'PHP -> Canvas', 'memory')),
                ], crossAxisAlignment: CrossAxisAlignment::STRETCH),
            ),
            width: $screenWidth,
            background: Tokens::surfaceMuted(),
        );
    }
}
