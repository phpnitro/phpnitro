<?php

namespace Engine\App;

use Engine\I18n\Translator;
use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\Banner;
use Engine\Native\Button;
use Engine\Native\Checkbox;
use Engine\Native\IconCircle;
use Engine\Native\PasswordField;
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
 * The native conversion of LoginPage.php — the one screen in this pass
 * that needed a genuinely new capability, not just recomposing existing
 * primitives: real keyboard text input. See TextField's docblock
 * for how that actually works (a real android.widget.EditText overlaid
 * at the tapped field's exact rect — there's no DOM input for the OS
 * keyboard to attach to on a Canvas).
 *
 * Also the reference example for Engine\I18n\Translator — every literal
 * string below is a t('login.*') lookup instead of a hardcoded French
 * string, see lib/lang/fr.php/en.php for the actual translations. Not
 * every screen in this framework has been converted yet (a real,
 * intentional scope boundary — this one exists to prove the pattern
 * works end to end, not to retrofit all ~40 screens in one pass).
 */
final class NativeLoginScreen
{
    public static function build(float $screenWidth, ?string $error): Widget
    {
        $contentWidth = $screenWidth - 2 * Tokens::SPACE_XL;
        $rememberMe = $_GET['remember'] ?? '';
        $errorMessage = $error !== null ? Translator::t('login.invalid_credentials') : null;

        return new Container(
            new Padding(
                EdgeInsets::all(Tokens::SPACE_XL),
                Flex::column([
                    new IconCircle('arrow_back', action: 'back'),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_LG),
                        new Text(Translator::t('login.title'), Tokens::TEXT_DISPLAY - 2, Tokens::ink()->toHex(), bold: true),
                    ),
                    new Padding(
                        EdgeInsets::only(top: 4),
                        new Text(Translator::t('login.demo_hint'), Tokens::TEXT_BODY_SMALL, Tokens::inkMuted()->toHex()),
                    ),
                    new Padding(EdgeInsets::only(top: Tokens::SPACE_LG), new Banner($errorMessage)),
                    new Padding(EdgeInsets::only(top: Tokens::SPACE_XL), new TextField('username', placeholder: Translator::t('login.username'))),
                    new Padding(EdgeInsets::only(top: Tokens::SPACE_MD), new PasswordField('password', placeholder: Translator::t('login.password'))),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_SM),
                        new RichText([
                            new TextSpan(Translator::t('login.forgot_password') . ' ', color: Tokens::inkMuted()->toHex()),
                            new TextSpan(Translator::t('login.forgot_password_link'), bold: true, color: Tokens::ink()->toHex(), action: 'navigate:forgot-password'),
                        ], fontSize: Tokens::TEXT_BODY_SMALL, color: Tokens::inkMuted()->toHex()),
                    ),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_LG),
                        new Checkbox('remember', Translator::t('login.remember_me'), $rememberMe === '1'),
                    ),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_XL),
                        new Button(Translator::t('login.submit'), 'submit:login', width: $contentWidth),
                    ),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_LG),
                        new RichText([
                            new TextSpan(Translator::t('login.no_account') . ' '),
                            new TextSpan(Translator::t('login.create_account'), bold: true, color: Tokens::ink()->toHex(), action: 'navigate:register'),
                        ], fontSize: Tokens::TEXT_BODY_SMALL, color: Tokens::inkMuted()->toHex()),
                    ),
                ], crossAxisAlignment: CrossAxisAlignment::STRETCH),
            ),
            width: $screenWidth,
            background: Tokens::surface(),
        );
    }
}
