<?php

namespace Engine\App;

use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\Banner;
use Engine\Native\Button;
use Engine\Native\Checkbox;
use Engine\Native\IconCircle;
use Engine\Native\TextField;
use Engine\Native\Container;
use Engine\Native\Flex;
use Engine\Native\Widget;
use Engine\Native\Padding;
use Engine\Native\Text;
use Engine\Native\Tokens;

/**
 * The native conversion of LoginPage.php — the one screen in this pass
 * that needed a genuinely new capability, not just recomposing existing
 * primitives: real keyboard text input. See TextField's docblock
 * for how that actually works (a real android.widget.EditText overlaid
 * at the tapped field's exact rect — there's no DOM input for the OS
 * keyboard to attach to on a Canvas).
 */
final class NativeLoginScreen
{
    public static function build(float $screenWidth, ?string $error): Widget
    {
        $contentWidth = $screenWidth - 2 * Tokens::SPACE_XL;
        $rememberMe = $_GET['remember'] ?? '';

        return new Container(
            new Padding(
                EdgeInsets::all(Tokens::SPACE_XL),
                Flex::column([
                    new IconCircle('arrow_back', action: 'back'),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_LG),
                        new Text('Connexion', Tokens::TEXT_DISPLAY - 2, Tokens::ink()->toHex(), bold: true),
                    ),
                    new Padding(
                        EdgeInsets::only(top: 4),
                        new Text('demo / demo', Tokens::TEXT_BODY_SMALL, Tokens::inkMuted()->toHex()),
                    ),
                    new Padding(EdgeInsets::only(top: Tokens::SPACE_LG), new Banner($error)),
                    new Padding(EdgeInsets::only(top: Tokens::SPACE_XL), new TextField('username', placeholder: 'Utilisateur')),
                    new Padding(EdgeInsets::only(top: Tokens::SPACE_MD), new TextField('password', placeholder: 'Mot de passe', obscure: true)),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_LG),
                        new Checkbox('remember', 'Se souvenir de moi', $rememberMe === '1'),
                    ),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_XL),
                        new Button('Se connecter', 'submit:login', width: $contentWidth),
                    ),
                ], crossAxisAlignment: CrossAxisAlignment::STRETCH),
            ),
            width: $screenWidth,
            background: Tokens::surface(),
        );
    }
}
