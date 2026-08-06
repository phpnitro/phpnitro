<?php

namespace Engine\App;

use Engine\Native\EdgeInsets;
use Engine\Native\Flexible;
use Engine\Native\MainAxisAlignment;
use Engine\Native\IconCircle;
use Engine\Native\Center;
use Engine\Native\Container;
use Engine\Native\Flex;
use Engine\Native\Widget;
use Engine\Native\Padding;
use Engine\Native\SizedBox;
use Engine\Native\Text;
use Engine\Native\Tokens;

/**
 * Modeled on captures/Vérification OTP.png — centered content (badge,
 * title, two-tone subtitle, 6 code boxes) with a Flexible spacer pushing
 * a disabled-looking CTA to the very bottom of the screen, same as the
 * reference. Needs a real screen HEIGHT (not just width) for that spacer
 * to mean anything — see the ?height= param on /native/layout-demo.
 *
 * The column below deliberately does NOT use CrossAxisAlignment::STRETCH
 * — that would force every child (including the 40x40 back button) to
 * fill the full width, same as real Flutter's CrossAxisAlignment.stretch
 * would. Instead each element that needs to be centered wraps itself in
 * Center individually, exactly the Center()-per-child pattern a
 * real Flutter build() method would use here.
 */
final class NativeOtpScreen
{
    public static function build(float $screenWidth, float $screenHeight): Widget
    {
        $contentWidth = $screenWidth - 2 * Tokens::SPACE_XL;

        $badge = new IconCircle('shield', 72);

        $otpBox = static fn (?string $digit, bool $active): Container => new Container(
            new Center(match (true) {
                $digit !== null => new Text($digit, 20, Tokens::ink()->toHex(), bold: true),
                $active => new Text('|', 20, Tokens::ink()->toHex(), bold: true),
                default => new Text('', 20, Tokens::ink()->toHex()),
            }),
            width: 46,
            height: 54,
            radius: Tokens::RADIUS_MD,
            background: $active ? Tokens::surface() : Tokens::surfaceMuted(),
            borderColor: $active ? Tokens::ink() : null,
            borderWidth: $active ? 1.5 : 0.0,
        );

        $otpRow = Flex::row([
            $otpBox('5', false),
            new Padding(EdgeInsets::only(left: Tokens::SPACE_SM), $otpBox(null, true)),
            new Padding(EdgeInsets::only(left: Tokens::SPACE_SM), $otpBox(null, false)),
            new Padding(EdgeInsets::only(left: Tokens::SPACE_SM), $otpBox(null, false)),
            new Padding(EdgeInsets::only(left: Tokens::SPACE_SM), $otpBox(null, false)),
            new Padding(EdgeInsets::only(left: Tokens::SPACE_SM), $otpBox(null, false)),
        ], mainAxisAlignment: MainAxisAlignment::CENTER);

        // TextMetrics::wrap() trims each text's content before measuring
        // (correct for real paragraph wrapping, but it means a trailing
        // space meant to separate two adjacent inline Text nodes
        // gets silently eaten) — an explicit gap avoids relying on a
        // space character surviving that trim.
        $subtitleRow = Flex::row([
            new Text('Code à 6 chiffres envoyé au', Tokens::TEXT_BODY_SMALL, Tokens::inkSecondary()->toHex()),
            new Padding(EdgeInsets::only(left: 4), new Text('+33 6 ••• ••• 42', Tokens::TEXT_BODY_SMALL, Tokens::ink()->toHex(), bold: true)),
        ], mainAxisAlignment: MainAxisAlignment::CENTER);

        return new Container(
            new Padding(
                EdgeInsets::all(Tokens::SPACE_XL),
                Flex::column([
                    Flex::row([
                        new IconCircle('arrow_back', action: 'back'),
                        // Real navigation to a third screen — showing
                        // genuinely live server data (packages/ui/src/Native/
                        // Tokens-styled), not another synthetic mockup.
                        new Flexible(new Container()),
                        new IconCircle('settings', action: 'navigate:settings'),
                    ]),
                    new Padding(EdgeInsets::only(top: Tokens::SPACE_XXL * 1.5), new Center($badge)),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_LG),
                        new Center(new Text('Vérifiez votre numéro', Tokens::TEXT_DISPLAY - 2, Tokens::ink()->toHex(), bold: true)),
                    ),
                    new Padding(EdgeInsets::only(top: Tokens::SPACE_SM), new Center($subtitleRow)),
                    new Padding(EdgeInsets::only(top: Tokens::SPACE_XXL), new Center($otpRow)),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        new Center(new Text('Renvoyer le code', Tokens::TEXT_BODY_SMALL, Tokens::inkSecondary()->toHex())),
                    ),
                    // Spacer: only meaningful because the root below is
                    // given a tight, real screen HEIGHT — a Flexible child
                    // with no explicit size just grows to consume whatever
                    // main-axis space is left, exactly like Flutter's
                    // Spacer(), pushing the CTA to the true bottom edge.
                    new Flexible(new SizedBox(0, 0)),
                    new Container(
                        new Center(new Text('Vérifier', Tokens::TEXT_BODY, Tokens::inkMuted()->toHex(), bold: true)),
                        width: $contentWidth,
                        height: 54,
                        background: Tokens::surfaceMuted(),
                        radius: Tokens::RADIUS_PILL,
                    ),
                ]),
            ),
            width: $screenWidth,
            height: $screenHeight,
            background: Tokens::surface(),
        );
    }
}
