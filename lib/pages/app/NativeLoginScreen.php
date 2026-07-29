<?php

namespace Engine\App;

use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\NativeBanner;
use Engine\Native\NativeButton;
use Engine\Native\NativeCheckbox;
use Engine\Native\NativeIconCircle;
use Engine\Native\NativeTextField;
use Engine\Native\RenderContainer;
use Engine\Native\RenderFlex;
use Engine\Native\RenderNode;
use Engine\Native\RenderPadding;
use Engine\Native\RenderText;
use Engine\Native\Tokens;

/**
 * The native conversion of LoginPage.php — the one screen in this pass
 * that needed a genuinely new capability, not just recomposing existing
 * primitives: real keyboard text input. See NativeTextField's docblock
 * for how that actually works (a real android.widget.EditText overlaid
 * at the tapped field's exact rect — there's no DOM input for the OS
 * keyboard to attach to on a Canvas).
 */
final class NativeLoginScreen
{
    public static function build(float $screenWidth, ?string $error): RenderNode
    {
        $contentWidth = $screenWidth - 2 * Tokens::SPACE_XL;
        $rememberMe = $_GET['remember'] ?? '';

        return new RenderContainer(
            new RenderPadding(
                EdgeInsets::all(Tokens::SPACE_XL),
                RenderFlex::column([
                    new NativeIconCircle('arrow_back', action: 'back'),
                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_LG),
                        new RenderText('Connexion', Tokens::TEXT_DISPLAY - 2, Tokens::ink()->toHex(), bold: true),
                    ),
                    new RenderPadding(
                        EdgeInsets::only(top: 4),
                        new RenderText('demo / demo', Tokens::TEXT_BODY_SMALL, Tokens::inkMuted()->toHex()),
                    ),
                    new RenderPadding(EdgeInsets::only(top: Tokens::SPACE_LG), new NativeBanner($error)),
                    new RenderPadding(EdgeInsets::only(top: Tokens::SPACE_XL), new NativeTextField('username', placeholder: 'Utilisateur')),
                    new RenderPadding(EdgeInsets::only(top: Tokens::SPACE_MD), new NativeTextField('password', placeholder: 'Mot de passe', obscure: true)),
                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_LG),
                        new NativeCheckbox('remember', 'Se souvenir de moi', $rememberMe === '1'),
                    ),
                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_XL),
                        new NativeButton('Se connecter', 'submit:login', width: $contentWidth),
                    ),
                ], crossAxisAlignment: CrossAxisAlignment::STRETCH),
            ),
            width: $screenWidth,
            background: Tokens::surface(),
        );
    }
}
