<?php

namespace Engine\App;

use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\NativeIconCircle;
use Engine\Native\NativeListTile;
use Engine\Native\RenderContainer;
use Engine\Native\RenderFlex;
use Engine\Native\RenderNode;
use Engine\Native\RenderPadding;
use Engine\Native\RenderText;
use Engine\Native\Tokens;

/**
 * La conversion native de WidgetsIndexPage.php — un simple menu de
 * navigation, chaque Link::make() devient une NativeListTile. Les
 * catégories qui n'ont pas encore d'écran natif dédié restent absentes
 * du menu plutôt que de pointer vers une route qui n'existe pas.
 */
final class NativeWidgetsIndexScreen
{
    public static function build(float $screenWidth): RenderNode
    {
        return new RenderContainer(
            new RenderPadding(
                EdgeInsets::all(Tokens::SPACE_XL),
                RenderFlex::column([
                    new NativeIconCircle('arrow_back', action: 'back'),
                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_LG),
                        new RenderText('Vitrine des widgets', Tokens::TEXT_DISPLAY - 2, Tokens::ink()->toHex(), bold: true),
                    ),
                    new RenderPadding(
                        EdgeInsets::only(top: 4),
                        new RenderText("Chaque catégorie montre les widgets natifs disponibles.", Tokens::TEXT_BODY_SMALL, Tokens::inkMuted()->toHex()),
                    ),
                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_XL),
                        new NativeListTile('Device', 'Vibreur, torche, batterie, notif...', 'smartphone', trailingIcon: 'chevron_right', action: 'navigate:device'),
                    ),
                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        new NativeListTile('Backend PHP', 'Appel API en-process', 'api', trailingIcon: 'chevron_right', action: 'navigate:api'),
                    ),
                ], crossAxisAlignment: CrossAxisAlignment::STRETCH),
            ),
            width: $screenWidth,
            background: Tokens::surfaceMuted(),
        );
    }
}
