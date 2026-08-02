<?php

namespace Engine\App;

use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\AppBar;
use Engine\Native\ListTile;
use Engine\Native\Scaffold;
use Engine\Native\Center;
use Engine\Native\Container;
use Engine\Native\Flex;
use Engine\Native\Lottie;
use Engine\Native\Widget;
use Engine\Native\Padding;
use Engine\Native\Text;
use Engine\Native\Tokens;

/**
 * Lottie's demo — a bundled asset (assets/lottie/pulse.json, a
 * hand-authored pulsing circle) rather than a remote lottiefiles.com URL,
 * so this screen works offline the same way the rest of the showcase
 * does. Lottie also accepts an https:// URL — NativeRenderPocActivity
 * tells the two apart by whether the string starts with "http".
 */
final class NativeWidgetsLottieScreen
{
    public static function build(float $screenWidth, float $screenHeight): Widget
    {
        $body = new Container(
            new Padding(
                EdgeInsets::all(Tokens::SPACE_XL),
                Flex::column([
                    new Text('Une vraie boucle Lottie — com.airbnb.android.lottie.LottieAnimationView réel, en overlay au-dessus du Canvas.', Tokens::TEXT_BODY_SMALL, Tokens::inkMuted()->toHex()),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_XL),
                        new Center(new Lottie('lottie/pulse.json', 160, 160)),
                    ),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_XL),
                        new ListTile('Splashscreen animé', 'Le même package, avec navigation automatique', 'auto_awesome', trailingIcon: 'chevron_right', action: 'navigate:widgets-splash'),
                    ),
                ], crossAxisAlignment: CrossAxisAlignment::STRETCH),
            ),
            width: $screenWidth,
            background: Tokens::surfaceMuted(),
        );

        return new Scaffold(
            $body,
            $screenWidth,
            $screenHeight,
            appBar: new AppBar($screenWidth, 'Lottie', backAction: 'back'),
        );
    }
}
