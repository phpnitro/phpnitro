<?php

namespace Engine\App;

use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\NativeAppBar;
use Engine\Native\NativeBanner;
use Engine\Native\NativeButton;
use Engine\Native\NativeConfirmButton;
use Engine\Native\NativeScaffold;
use Engine\Native\NativeTextField;
use Engine\Native\RenderContainer;
use Engine\Native\RenderFlex;
use Engine\Native\RenderNode;
use Engine\Native\RenderPadding;
use Engine\Native\RenderTappable;
use Engine\Native\RenderText;
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
    public static function build(float $screenWidth, float $screenHeight, ?string $error, string $mode): RenderNode
    {
        $uid = $_SESSION['firebase_uid'] ?? null;
        $contentWidth = $screenWidth - 2 * Tokens::SPACE_XL;
        $isSignUp = $mode === 'signup';

        $content = $uid !== null
            ? RenderFlex::column([
                new RenderText("Connecté — uid Firebase : {$uid}", Tokens::TEXT_BODY_SMALL, Tokens::inkSecondary()->toHex()),
                new RenderPadding(
                    EdgeInsets::only(top: Tokens::SPACE_LG),
                    new NativeConfirmButton('Se déconnecter ?', 'firebase_signout', 'Se déconnecter'),
                ),
            ], crossAxisAlignment: CrossAxisAlignment::STRETCH)
            : RenderFlex::column([
                new NativeBanner($error),
                new RenderPadding(EdgeInsets::only(top: $error !== null ? Tokens::SPACE_LG : 0), new NativeTextField('email', placeholder: 'Email')),
                new RenderPadding(EdgeInsets::only(top: Tokens::SPACE_MD), new NativeTextField('password', placeholder: 'Mot de passe', obscure: true)),
                new RenderPadding(
                    EdgeInsets::only(top: Tokens::SPACE_XL),
                    new NativeButton($isSignUp ? 'Créer un compte' : 'Se connecter', $isSignUp ? 'submit:signup' : 'submit:signin', width: $contentWidth),
                ),
                new RenderPadding(
                    EdgeInsets::only(top: Tokens::SPACE_LG),
                    new RenderTappable(
                        new RenderText(
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

        $body = new RenderContainer(
            new RenderPadding(EdgeInsets::all(Tokens::SPACE_XL), $content),
            width: $screenWidth,
            background: Tokens::surfaceMuted(),
        );

        return new NativeScaffold(
            $body,
            $screenWidth,
            $screenHeight,
            appBar: new NativeAppBar($screenWidth, 'Firebase Authentication', backAction: 'back'),
        );
    }
}
