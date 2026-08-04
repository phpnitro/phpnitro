<?php

namespace Engine\App;

use Engine\I18n\Translator;
use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\Banner;
use Engine\Native\Button;
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
 * Real account creation — the counterpart NativeLoginScreen needed once
 * login stopped being a hardcoded "demo/demo" check (see
 * Backend\Repository\UserRepository). Same TextField-overlay input
 * mechanism as NativeLoginScreen, just three fields instead of two.
 *
 * Static UI strings go through Translator::t() (see lib/lang/fr.php/
 * en.php's 'register.*' keys) the same as NativeLoginScreen — $error
 * itself is passed through untranslated, since public/index.php's
 * register handler produces several distinct validation messages
 * (missing fields, too-short password, mismatch, username taken), not
 * one fixed string a single translation key could stand in for.
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
                        new Text(Translator::t('register.title'), Tokens::TEXT_DISPLAY - 2, Tokens::ink()->toHex(), bold: true),
                    ),
                    new Padding(EdgeInsets::only(top: Tokens::SPACE_LG), new Banner($error)),
                    new Padding(EdgeInsets::only(top: Tokens::SPACE_XL), new TextField('username', placeholder: Translator::t('login.username'))),
                    new Padding(EdgeInsets::only(top: Tokens::SPACE_MD), new PasswordField('password', placeholder: Translator::t('register.password_hint'))),
                    new Padding(EdgeInsets::only(top: Tokens::SPACE_MD), new PasswordField('password_confirm', placeholder: Translator::t('register.confirm_password'))),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_XL),
                        new Button(Translator::t('register.submit'), 'submit:register', width: $contentWidth),
                    ),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_LG),
                        new RichText([
                            new TextSpan(Translator::t('register.has_account') . ' '),
                            new TextSpan(Translator::t('register.login_link'), bold: true, color: Tokens::ink()->toHex(), action: 'navigate:login'),
                        ], fontSize: Tokens::TEXT_BODY_SMALL, color: Tokens::inkMuted()->toHex()),
                    ),
                ], crossAxisAlignment: CrossAxisAlignment::STRETCH),
            ),
            width: $screenWidth,
            background: Tokens::surface(),
        );
    }
}
