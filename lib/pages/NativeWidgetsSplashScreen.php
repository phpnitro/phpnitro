<?php

namespace Engine\App;

use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\MainAxisAlignment;
use Engine\Native\Center;
use Engine\Native\Container;
use Engine\Native\Flex;
use Engine\Native\Lottie;
use Engine\Native\Widget;
use Engine\Native\Padding;
use Engine\Native\Splash;
use Engine\Native\Text;
use Engine\Native\Tokens;

/**
 * Splash's demo — a centered logo animation (the same bundled Lottie
 * pulse.json Lottie's own demo uses) plus a brand line, wrapped so it
 * pushes back to 'widgets' on its own after $durationMs. No app bar/back
 * button: a splash is meant to be looked at, not navigated within, the
 * same way NativeHomeScreen doesn't show one during the OS-level splash.
 */
final class NativeWidgetsSplashScreen
{
    public static function build(float $screenWidth, float $screenHeight): Widget
    {
        $content = new Container(
            new Center(
                Flex::column([
                    new Lottie('lottie/pulse.json', 160, 160),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_LG),
                        new Text('PhpNitro', Tokens::TEXT_TITLE, Tokens::ink()->toHex(), bold: true),
                    ),
                ], crossAxisAlignment: CrossAxisAlignment::CENTER, mainAxisAlignment: MainAxisAlignment::CENTER),
            ),
            width: $screenWidth,
            height: $screenHeight,
            background: Tokens::surface(),
        );

        return new Splash($content, 'widgets', 2200);
    }
}
