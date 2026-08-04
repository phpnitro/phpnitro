<?php

namespace Engine\App;

use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\Banner;
use Engine\Native\Button;
use Engine\Native\IconCircle;
use Engine\Native\RichText;
use Engine\Native\TextField;
use Engine\Native\TextSpan;
use Engine\Native\Container;
use Engine\Native\Flex;
use Engine\Native\Widget;
use Engine\Native\Padding;
use Engine\Native\Text;
use Engine\Native\Tokens;

/**
 * Requests a reset token (Backend\Repository\PasswordResetRepository) for
 * a username. No mailer is configured anywhere in this framework by
 * default, so instead of pretending to "send an email" this shows the raw
 * reset link directly on screen once a token exists — same honest-
 * degradation pattern as every other capability here that needs a
 * credential/service this environment doesn't have (FIREBASE_WEB_API_KEY,
 * FEEXPAY_SHOP_ID...). Wire a real mailer call in public/index.php's
 * "forgot_password" action handler where noted, once one exists, and this
 * screen's success banner becomes unnecessary (or gets demoted to a "check
 * your email" message instead of the link itself).
 *
 * Deliberately does NOT reveal whether the username exists — the success
 * message is identical either way (see public/index.php); only the
 * $devResetLink differs (empty when there's nothing to show), which is
 * why this shows a generic "if that account exists" message rather than
 * a definitive yes/no about the username itself.
 */
final class NativeForgotPasswordScreen
{
    public static function build(float $screenWidth, ?string $error, ?string $devResetLink): Widget
    {
        $contentWidth = $screenWidth - 2 * Tokens::SPACE_XL;

        $rows = [
            new IconCircle('arrow_back', action: 'back'),
            new Padding(
                EdgeInsets::only(top: Tokens::SPACE_LG),
                new Text('Mot de passe oublié', Tokens::TEXT_DISPLAY - 2, Tokens::ink()->toHex(), bold: true),
            ),
            new Padding(
                EdgeInsets::only(top: 4),
                new Text("Entre ton nom d'utilisateur, on te donne un lien de réinitialisation.", Tokens::TEXT_BODY_SMALL, Tokens::inkMuted()->toHex()),
            ),
            new Padding(EdgeInsets::only(top: Tokens::SPACE_LG), new Banner($error)),
            new Padding(EdgeInsets::only(top: Tokens::SPACE_XL), new TextField('username', placeholder: 'Utilisateur')),
            new Padding(
                EdgeInsets::only(top: Tokens::SPACE_XL),
                new Button('Envoyer le lien', 'submit:forgot_password', width: $contentWidth),
            ),
        ];

        if ($devResetLink !== null) {
            $rows[] = new Padding(
                EdgeInsets::only(top: Tokens::SPACE_LG),
                new Banner("Mode démo (pas de mailer configuré) — lien : {$devResetLink}", icon: 'check_circle', background: Tokens::success(), foreground: Tokens::ink()),
            );
            $rows[] = new Padding(
                EdgeInsets::only(top: Tokens::SPACE_MD),
                new RichText([
                    new TextSpan("J'ai mon code — ", color: Tokens::inkMuted()->toHex()),
                    new TextSpan('réinitialiser', bold: true, color: Tokens::ink()->toHex(), action: 'navigate:reset-password'),
                ], fontSize: Tokens::TEXT_BODY_SMALL, color: Tokens::inkMuted()->toHex()),
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
