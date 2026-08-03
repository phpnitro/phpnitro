<?php

namespace Engine\App;

use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\Banner;
use Engine\Native\Button;
use Engine\Native\IconCircle;
use Engine\Native\TextField;
use Engine\Native\Container;
use Engine\Native\Flex;
use Engine\Native\RichText;
use Engine\Native\TextSpan;
use Engine\Native\Widget;
use Engine\Native\Padding;
use Engine\Native\Text;
use Engine\Native\Tokens;

/**
 * Real account creation — the counterpart NativeLoginScreen needed once
 * login stopped being a hardcoded "demo/demo" check (see
 * Backend\Repository\UserRepository). Same TextField-overlay input
 * mechanism as NativeLoginScreen, just three fields instead of two.
 */
final class NativeRegisterScreen
{
    public static function build(float $screenWidth, ?string $error): Widget
    {
        $contentWidth = $screenWidth - 2 * Tokens::SPACE_XL;

        return new Container(
            new Padding(
                EdgeInsets::all(Tokens::SPACE_XL),
                Flex::column([
                    new IconCircle('arrow_back', action: 'back'),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_LG),
                        new Text('Créer un compte', Tokens::TEXT_DISPLAY - 2, Tokens::ink()->toHex(), bold: true),
                    ),
                    new Padding(EdgeInsets::only(top: Tokens::SPACE_LG), new Banner($error)),
                    new Padding(EdgeInsets::only(top: Tokens::SPACE_XL), new TextField('username', placeholder: 'Utilisateur')),
                    new Padding(EdgeInsets::only(top: Tokens::SPACE_MD), new TextField('password', placeholder: 'Mot de passe (6 caractères min.)', obscure: true)),
                    new Padding(EdgeInsets::only(top: Tokens::SPACE_MD), new TextField('password_confirm', placeholder: 'Confirmer le mot de passe', obscure: true)),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_XL),
                        new Button('Créer le compte', 'submit:register', width: $contentWidth),
                    ),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_LG),
                        new RichText([
                            new TextSpan('Déjà un compte ? '),
                            new TextSpan('Se connecter', bold: true, color: Tokens::ink()->toHex(), action: 'navigate:login'),
                        ], fontSize: Tokens::TEXT_BODY_SMALL, color: Tokens::inkMuted()->toHex()),
                    ),
                ], crossAxisAlignment: CrossAxisAlignment::STRETCH),
            ),
            width: $screenWidth,
            background: Tokens::surface(),
        );
    }
}
