<?php

namespace Engine\App;

use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\NativeAppBar;
use Engine\Native\NativeListTile;
use Engine\Native\NativeScaffold;
use Engine\Native\RenderCenter;
use Engine\Native\RenderContainer;
use Engine\Native\RenderFlex;
use Engine\Native\RenderLottie;
use Engine\Native\RenderNode;
use Engine\Native\RenderPadding;
use Engine\Native\RenderText;
use Engine\Native\Tokens;

/**
 * RenderLottie's demo — a bundled asset (assets/lottie/pulse.json, a
 * hand-authored pulsing circle) rather than a remote lottiefiles.com URL,
 * so this screen works offline the same way the rest of the showcase
 * does. RenderLottie also accepts an https:// URL — NativeRenderPocActivity
 * tells the two apart by whether the string starts with "http".
 */
final class NativeWidgetsLottieScreen
{
    public static function build(float $screenWidth, float $screenHeight): RenderNode
    {
        $body = new RenderContainer(
            new RenderPadding(
                EdgeInsets::all(Tokens::SPACE_XL),
                RenderFlex::column([
                    new RenderText('Une vraie boucle Lottie — com.airbnb.android.lottie.LottieAnimationView réel, en overlay au-dessus du Canvas.', Tokens::TEXT_BODY_SMALL, Tokens::inkMuted()->toHex()),
                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_XL),
                        new RenderCenter(new RenderLottie('lottie/pulse.json', 160, 160)),
                    ),
                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_XL),
                        new NativeListTile('Splashscreen animé', 'Le même package, avec navigation automatique', 'auto_awesome', trailingIcon: 'chevron_right', action: 'navigate:widgets-splash'),
                    ),
                ], crossAxisAlignment: CrossAxisAlignment::STRETCH),
            ),
            width: $screenWidth,
            background: Tokens::surfaceMuted(),
        );

        return new NativeScaffold(
            $body,
            $screenWidth,
            $screenHeight,
            appBar: new NativeAppBar($screenWidth, 'Lottie', backAction: 'back'),
        );
    }
}
