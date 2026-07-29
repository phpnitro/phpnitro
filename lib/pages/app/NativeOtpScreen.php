<?php

namespace Engine\App;

use Engine\Native\EdgeInsets;
use Engine\Native\Flexible;
use Engine\Native\MainAxisAlignment;
use Engine\Native\NativeIconCircle;
use Engine\Native\RenderCenter;
use Engine\Native\RenderContainer;
use Engine\Native\RenderFlex;
use Engine\Native\RenderNode;
use Engine\Native\RenderPadding;
use Engine\Native\RenderSizedBox;
use Engine\Native\RenderText;
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
 * RenderCenter individually, exactly the Center()-per-child pattern a
 * real Flutter build() method would use here.
 */
final class NativeOtpScreen
{
    public static function build(float $screenWidth, float $screenHeight): RenderNode
    {
        $contentWidth = $screenWidth - 2 * Tokens::SPACE_XL;

        $badge = new NativeIconCircle('shield', 72);

        $otpBox = static fn (?string $digit, bool $active): RenderContainer => new RenderContainer(
            new RenderCenter(match (true) {
                $digit !== null => new RenderText($digit, 20, Tokens::ink()->toHex(), bold: true),
                $active => new RenderText('|', 20, Tokens::ink()->toHex(), bold: true),
                default => new RenderText('', 20, Tokens::ink()->toHex()),
            }),
            width: 46,
            height: 54,
            radius: Tokens::RADIUS_MD,
            background: $active ? Tokens::surface() : Tokens::surfaceMuted(),
            borderColor: $active ? Tokens::ink() : null,
            borderWidth: $active ? 1.5 : 0.0,
        );

        $otpRow = RenderFlex::row([
            $otpBox('5', false),
            new RenderPadding(EdgeInsets::only(left: Tokens::SPACE_SM), $otpBox(null, true)),
            new RenderPadding(EdgeInsets::only(left: Tokens::SPACE_SM), $otpBox(null, false)),
            new RenderPadding(EdgeInsets::only(left: Tokens::SPACE_SM), $otpBox(null, false)),
            new RenderPadding(EdgeInsets::only(left: Tokens::SPACE_SM), $otpBox(null, false)),
            new RenderPadding(EdgeInsets::only(left: Tokens::SPACE_SM), $otpBox(null, false)),
        ], mainAxisAlignment: MainAxisAlignment::CENTER);

        // TextMetrics::wrap() trims each text's content before measuring
        // (correct for real paragraph wrapping, but it means a trailing
        // space meant to separate two adjacent inline RenderText nodes
        // gets silently eaten) — an explicit gap avoids relying on a
        // space character surviving that trim.
        $subtitleRow = RenderFlex::row([
            new RenderText('Code à 6 chiffres envoyé au', Tokens::TEXT_BODY_SMALL, Tokens::inkSecondary()->toHex()),
            new RenderPadding(EdgeInsets::only(left: 4), new RenderText('+33 6 ••• ••• 42', Tokens::TEXT_BODY_SMALL, Tokens::ink()->toHex(), bold: true)),
        ], mainAxisAlignment: MainAxisAlignment::CENTER);

        return new RenderContainer(
            new RenderPadding(
                EdgeInsets::all(Tokens::SPACE_XL),
                RenderFlex::column([
                    RenderFlex::row([
                        new NativeIconCircle('arrow_back', action: 'back'),
                        // Real navigation to a third screen — showing
                        // genuinely live server data (packages/ui/src/Native/
                        // Tokens-styled), not another synthetic mockup.
                        new Flexible(new RenderContainer()),
                        new NativeIconCircle('settings', action: 'navigate:settings'),
                    ]),
                    new RenderPadding(EdgeInsets::only(top: Tokens::SPACE_XXL * 1.5), new RenderCenter($badge)),
                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_LG),
                        new RenderCenter(new RenderText('Vérifiez votre numéro', Tokens::TEXT_DISPLAY - 2, Tokens::ink()->toHex(), bold: true)),
                    ),
                    new RenderPadding(EdgeInsets::only(top: Tokens::SPACE_SM), new RenderCenter($subtitleRow)),
                    new RenderPadding(EdgeInsets::only(top: Tokens::SPACE_XXL), new RenderCenter($otpRow)),
                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        new RenderCenter(new RenderText('Renvoyer le code', Tokens::TEXT_BODY_SMALL, Tokens::inkSecondary()->toHex())),
                    ),
                    // Spacer: only meaningful because the root below is
                    // given a tight, real screen HEIGHT — a Flexible child
                    // with no explicit size just grows to consume whatever
                    // main-axis space is left, exactly like Flutter's
                    // Spacer(), pushing the CTA to the true bottom edge.
                    new Flexible(new RenderSizedBox(0, 0)),
                    new RenderContainer(
                        new RenderCenter(new RenderText('Vérifier', Tokens::TEXT_BODY, Tokens::inkMuted()->toHex(), bold: true)),
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
