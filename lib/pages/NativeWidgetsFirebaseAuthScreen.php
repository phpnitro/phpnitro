<?php

namespace Engine\App;

use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\AppBar;
use Engine\Native\Banner;
use Engine\Native\Button;
use Engine\Native\Center;
use Engine\Color;
use Engine\Native\ConfirmButton;
use Engine\Native\ImageCircle;
use Engine\Native\MainAxisAlignment;
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
    public static function build(float $screenWidth, float $screenHeight, ?string $error, string $mode, ?string $githubAuthorizeUrl = null, ?string $facebookAuthorizeUrl = null): Widget
    {
        $uid = $_SESSION['firebase_uid'] ?? null;
        $socialUser = $_SESSION['social_user'] ?? null;
        $contentWidth = $screenWidth - 2 * Tokens::SPACE_XL;
        $isSignUp = $mode === 'signup';
        // Same convention NativeWidgetsMediaScreen.php's beep.wav uses —
        // HTTP_HOST reflects whatever the client actually connected to
        // (127.0.0.1:port embedded, or a LAN IP:port for PhpNitro Go's
        // remote mode), so this resolves correctly either way. The PNG
        // itself is Google's own officially-documented Sign-In icon
        // asset (assets/images/google_logo.png), not a redrawn
        // approximation — Google's brand guidelines require their actual
        // logo for this exact "Sign in with Google" button use case.
        $googleLogoUrl = 'http://' . ($_SERVER['HTTP_HOST'] ?? '127.0.0.1') . '/assets/images/google_logo.png';

        $content = match (true) {
            $uid !== null => Flex::column([
                new Text("Connecté — uid Firebase : {$uid}", Tokens::TEXT_BODY_SMALL, Tokens::inkSecondary()->toHex()),
                new Padding(
                    EdgeInsets::only(top: Tokens::SPACE_LG),
                    new ConfirmButton('Se déconnecter ?', 'firebase_signout', 'Se déconnecter'),
                ),
            ], crossAxisAlignment: CrossAxisAlignment::STRETCH),
            // GitHub/Facebook/Microsoft/Apple land here — see
            // OAuthProvider::exchangeCode()'s normalized
            // {id, email, name, access_token} shape, stored as-is rather
            // than in a real Users table (same demo-only "$_SESSION
            // stands in for a database row" idiom
            // Dismissible/Reorderable already use elsewhere in this
            // showcase — a real app would look up/create a local account
            // keyed by provider+id here instead).
            $socialUser !== null => Flex::column([
                new Text(
                    "Connecté via {$socialUser['provider']} — " . ($socialUser['name'] ?? $socialUser['email'] ?? $socialUser['id']),
                    Tokens::TEXT_BODY_SMALL,
                    Tokens::inkSecondary()->toHex(),
                ),
                new Padding(
                    EdgeInsets::only(top: Tokens::SPACE_LG),
                    new ConfirmButton('Se déconnecter ?', 'social_signout', 'Se déconnecter'),
                ),
            ], crossAxisAlignment: CrossAxisAlignment::STRETCH),
            default => Flex::column([
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
                new Padding(
                    EdgeInsets::only(top: Tokens::SPACE_LG),
                    new Text('— ou —', Tokens::TEXT_BODY_SMALL, Tokens::inkMuted()->toHex()),
                ),
                // Credential Manager's Google ID-token flow — see
                // NativeDeviceBridge.kt's signInWithGoogle() and this
                // action's handling in NativeRenderPocActivity's
                // handleDeviceAction(). Needs a real Google Cloud OAuth
                // Web Client ID configured (see that method's own
                // docblock) to do anything beyond fail informatively; not
                // wired up automatically since that credential is
                // per-project, the same way FIREBASE_WEB_API_KEY above
                // is.
                //
                // White pill + real logo + grey border, not a solid Button
                // — this is Google's own documented button style for
                // "Sign in with Google" (a plain dark Button with generic
                // text is explicitly what their brand guidelines say NOT
                // to do). ImageCircle already renders arbitrary images in
                // a circle (see NativeSettingsScreen.php's avatar); wrapping
                // the whole row in Tappable is all a "branded icon button"
                // needs — no engine change required, Icon/IconCircle's
                // MaterialIcons-only glyphs were never actually the
                // blocker.
                //
                // Container alone does NOT center a child narrower than
                // its own declared width — layout() hands the child a
                // LOOSENED constraint (see Container::layout()), so a Row
                // that hugs its own content size just paints at the
                // container's top-left corner. Button.php's own "icon +
                // label" branch wraps in Center() for exactly this reason;
                // this needed the same wrapper.
                new Padding(
                    EdgeInsets::only(top: Tokens::SPACE_MD),
                    new Tappable(
                        new Container(
                            new Center(Flex::row([
                                new ImageCircle($googleLogoUrl, 24.0),
                                new Padding(
                                    EdgeInsets::only(left: Tokens::SPACE_SM),
                                    new Text('Continuer avec Google', Tokens::TEXT_BODY, Tokens::ink()->toHex(), bold: true),
                                ),
                            ], mainAxisAlignment: MainAxisAlignment::CENTER, crossAxisAlignment: CrossAxisAlignment::CENTER)),
                            width: $contentWidth,
                            height: 54.0,
                            background: Color::white(),
                            radius: 27.0,
                            borderColor: Tokens::border(),
                            borderWidth: 1.5,
                        ),
                        'device:googlesignin',
                    ),
                ),
                // GitHub/Facebook — see Engine\SocialAuth\OAuthProvider's
                // docblock and NativeDeviceBridge.kt's startOAuthFlow().
                // $githubAuthorizeUrl/$facebookAuthorizeUrl are null when
                // that provider's client_id isn't configured in .env
                // (public/index.php only builds them when it is) — the
                // button still renders either way, matching Google's own
                // "always visible, fails informatively on tap" story
                // rather than disappearing when unconfigured.
                new Padding(
                    EdgeInsets::only(top: Tokens::SPACE_MD),
                    self::providerButton('Continuer avec GitHub', 'github', $githubAuthorizeUrl, $contentWidth),
                ),
                new Padding(
                    EdgeInsets::only(top: Tokens::SPACE_MD),
                    self::providerButton('Continuer avec Facebook', 'facebook', $facebookAuthorizeUrl, $contentWidth),
                ),
            ], crossAxisAlignment: CrossAxisAlignment::STRETCH),
        };

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

    /**
     * Same white-pill-with-border shape as the Google button above, minus
     * the branded logo (no official GitHub/Facebook asset bundled here —
     * see this class's own docblock for why Google's IS a real logo
     * asset: their brand guidelines require it for this exact button,
     * GitHub/Facebook's don't as strictly). $authorizeUrl null means
     * unconfigured — the Tappable still fires "device:oauth:{provider}"
     * either way, meta.url just comes back empty, which is what makes
     * NativeRenderPocActivity's dispatch fail informatively instead of
     * opening a Custom Tab to a request missing its client_id.
     */
    private static function providerButton(string $label, string $provider, ?string $authorizeUrl, float $width): Widget
    {
        return new Tappable(
            new Container(
                new Center(new Text($label, Tokens::TEXT_BODY, Tokens::ink()->toHex(), bold: true)),
                width: $width,
                height: 54.0,
                background: Color::white(),
                radius: 27.0,
                borderColor: Tokens::border(),
                borderWidth: 1.5,
            ),
            "device:oauth:{$provider}",
            ['url' => $authorizeUrl ?? ''],
        );
    }
}
