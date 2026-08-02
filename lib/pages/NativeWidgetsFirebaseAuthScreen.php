<?php

namespace Engine\App;

use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\AppBar;
use Engine\Native\Banner;
use Engine\Native\Button;
use Engine\Native\ConfirmButton;
use Engine\Native\Scaffold;
use Engine\Native\TextField;
use Engine\Native\Container;
use Engine\Native\Flex;
use Engine\Native\Widget;
use Engine\Native\Padding;
use Engine\Native\Tappable;
use Engine\Native\Text;
use Engine\Native\Tokens;

/**
 * The native conversion of WidgetsFirebaseAuthPage.php — Engine\Firebase\
 * FirebaseAuth::signIn()/signUp() is a plain server-side REST call (no
 * client SDK/JS needed), so this ports with the same building blocks
 * NativeLoginScreen already proved. One toggleable form instead of two
 * stacked ones (email/password would otherwise collide as field names
 * across both forms, unlike separate <form> elements in the HTML
 * pipeline, which scope fields per-form) — a "Créer un compte" link
 * switches $_GET['fb_mode'] between signin/signup.
 */
final class NativeWidgetsFirebaseAuthScreen
{
    public static function build(float $screenWidth, float $screenHeight, ?string $error, string $mode): Widget
    {
        $uid = $_SESSION['firebase_uid'] ?? null;
        $contentWidth = $screenWidth - 2 * Tokens::SPACE_XL;
        $isSignUp = $mode === 'signup';

        $content = $uid !== null
            ? Flex::column([
                new Text("Connecté — uid Firebase : {$uid}", Tokens::TEXT_BODY_SMALL, Tokens::inkSecondary()->toHex()),
                new Padding(
                    EdgeInsets::only(top: Tokens::SPACE_LG),
                    new ConfirmButton('Se déconnecter ?', 'firebase_signout', 'Se déconnecter'),
                ),
            ], crossAxisAlignment: CrossAxisAlignment::STRETCH)
            : Flex::column([
                new Banner($error),
                new Padding(EdgeInsets::only(top: $error !== null ? Tokens::SPACE_LG : 0), new TextField('email', placeholder: 'Email')),
                new Padding(EdgeInsets::only(top: Tokens::SPACE_MD), new TextField('password', placeholder: 'Mot de passe', obscure: true)),
                new Padding(
                    EdgeInsets::only(top: Tokens::SPACE_XL),
                    new Button($isSignUp ? 'Créer un compte' : 'Se connecter', $isSignUp ? 'submit:signup' : 'submit:signin', width: $contentWidth),
                ),
                new Padding(
                    EdgeInsets::only(top: Tokens::SPACE_LG),
                    new Tappable(
                        new Text(
                            $isSignUp ? 'Déjà un compte ? Se connecter' : "Pas de compte ? En créer un",
                            Tokens::TEXT_BODY_SMALL,
                            Tokens::inkSecondary()->toHex(),
                            bold: true,
                        ),
                        'toggle:fb_mode',
                        ['next' => $isSignUp ? 'signin' : 'signup'],
                    ),
                ),
            ], crossAxisAlignment: CrossAxisAlignment::STRETCH);

        $body = new Container(
            new Padding(EdgeInsets::all(Tokens::SPACE_XL), $content),
            width: $screenWidth,
            background: Tokens::surfaceMuted(),
        );

        return new Scaffold(
            $body,
            $screenWidth,
            $screenHeight,
            appBar: new AppBar($screenWidth, 'Firebase Authentication', backAction: 'back'),
        );
    }
}
