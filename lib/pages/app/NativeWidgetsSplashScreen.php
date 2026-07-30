<?php

namespace Engine\App;

use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\MainAxisAlignment;
use Engine\Native\RenderCenter;
use Engine\Native\RenderContainer;
use Engine\Native\RenderFlex;
use Engine\Native\RenderLottie;
use Engine\Native\RenderNode;
use Engine\Native\RenderPadding;
use Engine\Native\RenderSplash;
use Engine\Native\RenderText;
use Engine\Native\Tokens;

/**
 * RenderSplash's demo — a centered logo animation (the same bundled Lottie
 * pulse.json RenderLottie's own demo uses) plus a brand line, wrapped so it
 * pushes back to 'widgets' on its own after $durationMs. No app bar/back
 * button: a splash is meant to be looked at, not navigated within, the
 * same way NativeHomeScreen doesn't show one during the OS-level splash.
 */
final class NativeWidgetsSplashScreen
{
    public static function build(float $screenWidth, float $screenHeight): RenderNode
    {
        $content = new RenderContainer(
            new RenderCenter(
                RenderFlex::column([
                    new RenderLottie('lottie/pulse.json', 160, 160),
                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_LG),
                        new RenderText('PhpNitro', Tokens::TEXT_TITLE, Tokens::ink()->toHex(), bold: true),
                    ),
                ], crossAxisAlignment: CrossAxisAlignment::CENTER, mainAxisAlignment: MainAxisAlignment::CENTER),
            ),
            width: $screenWidth,
            height: $screenHeight,
            background: Tokens::surface(),
        );

        return new RenderSplash($content, 'widgets', 2200);
    }
}
