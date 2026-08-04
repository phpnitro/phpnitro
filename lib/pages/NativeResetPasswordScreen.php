<?php

namespace Engine\App;

use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\Banner;
use Engine\Native\Button;
use Engine\Native\IconCircle;
use Engine\Native\PasswordField;
use Engine\Native\TextField;
use Engine\Native\Container;
use Engine\Native\Flex;
use Engine\Native\Widget;
use Engine\Native\Padding;
use Engine\Native\Text;
use Engine\Native\Tokens;

/**
 * The token field is a plain TextField, not something auto-filled from a
 * real deep link — this framework has no universal-links/App-Links setup
 * for a password-reset URL to land here directly (unlike the OAuth
 * callback's phpnitro:// custom scheme, a reset link is meant to be
 * copy/pasted or opened from an actual email in a real deployment), so
 * NativeForgotPasswordScreen's dev-mode banner shows the raw token for
 * the user to paste here. See Backend\Repository\PasswordResetRepository
 * for the actual verify/consume logic.
 */
final class NativeResetPasswordScreen
{
    public static function build(float $screenWidth, ?string $error, ?string $successMessage): Widget
    {
        $contentWidth = $screenWidth - 2 * Tokens::SPACE_XL;

        $rows = [
            new IconCircle('arrow_back', action: 'back'),
            new Padding(
                EdgeInsets::only(top: Tokens::SPACE_LG),
                new Text('Nouveau mot de passe', Tokens::TEXT_DISPLAY - 2, Tokens::ink()->toHex(), bold: true),
            ),
            new Padding(EdgeInsets::only(top: Tokens::SPACE_LG), new Banner($error)),
        ];

        if ($successMessage !== null) {
            $rows[] = new Padding(
                EdgeInsets::only(top: Tokens::SPACE_LG),
                new Banner($successMessage, icon: 'check_circle', background: Tokens::success(), foreground: Tokens::ink()),
            );
            $rows[] = new Padding(
                EdgeInsets::only(top: Tokens::SPACE_LG),
                new Button('Se connecter', 'navigate:login', width: $contentWidth),
            );
        } else {
            $rows[] = new Padding(EdgeInsets::only(top: Tokens::SPACE_XL), new TextField('reset_token', placeholder: 'Code reçu'));
            $rows[] = new Padding(EdgeInsets::only(top: Tokens::SPACE_MD), new PasswordField('new_password', placeholder: 'Nouveau mot de passe (6 caractères min.)'));
            $rows[] = new Padding(EdgeInsets::only(top: Tokens::SPACE_MD), new PasswordField('new_password_confirm', placeholder: 'Confirmer le mot de passe'));
            $rows[] = new Padding(
                EdgeInsets::only(top: Tokens::SPACE_XL),
                new Button('Réinitialiser', 'submit:reset_password', width: $contentWidth),
            );
        }

        return new Container(
            new Padding(
                EdgeInsets::all(Tokens::SPACE_XL),
                Flex::column($rows, crossAxisAlignment: CrossAxisAlignment::STRETCH),
            ),
            width: $screenWidth,
            background: Tokens::surface(),
        );
    }
}
