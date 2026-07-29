<?php

namespace Engine\App;

use Engine\Color;
use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\Flexible;
use Engine\Native\NativeButton;
use Engine\Native\NativeCard;
use Engine\Native\NativeIconCircle;
use Engine\Native\NativeListTile;
use Engine\Native\RenderContainer;
use Engine\Native\RenderFlex;
use Engine\Native\RenderNode;
use Engine\Native\RenderPadding;
use Engine\Native\RenderText;
use Engine\Native\Tokens;
use Engine\Preferences\Preferences;

/**
 * The native conversion of HomePage.php (the real app's actual landing
 * screen, not another reference-image recreation) — same real session
 * auth check, same real persisted counter (via Engine\Preferences\,
 * same primitive NativeSettingsScreen already reads/writes), same
 * navigation targets. What's deliberately NOT carried over 1:1:
 *
 * - GestureDetector's double-click/swipe interactions collapse to a
 *   single tap on a NativeButton — this engine's hit-testing is
 *   tap-only for now, no gesture disambiguation beyond scroll-vs-tap.
 * - The Drawer (slide-in side panel) and FloatingActionButton (an
 *   overlay outside normal layout flow) have no native equivalent yet —
 *   RenderStack exists but is top-left-only (see its docblock), not
 *   full Positioned-style placement. The drawer's destinations are
 *   reachable as plain NativeListTile rows instead.
 *
 * Root of the native screen stack — NativeRenderPocActivity starts here
 * by default; Documents/OTP/Settings are all one "back" away from it.
 */
final class NativeHomeScreen
{
    public static function build(float $screenWidth): RenderNode
    {
        $authUser = $_SESSION['auth_user'] ?? null;
        // A separate Preferences key from NativeDocumentsScreen's
        // file-backed tap count — same real round-trip demonstration,
        // deliberately not the same counter (they mean different things
        // and shouldn't share state just because both are "a number that
        // goes up").
        $count = (int) Preferences::get('native_home_counter', '0');

        return new RenderContainer(
            new RenderPadding(
                EdgeInsets::all(Tokens::SPACE_XL),
                RenderFlex::column([
                    RenderFlex::row([
                        new RenderText('Mon application', Tokens::TEXT_DISPLAY - 2, Tokens::ink()->toHex(), bold: true),
                        new Flexible(new RenderContainer()),
                        new NativeIconCircle('settings', action: 'navigate:settings'),
                    ], crossAxisAlignment: CrossAxisAlignment::CENTER),
                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_SM),
                        new RenderText(
                            $authUser !== null ? "Connecté : {$authUser}" : 'Non connecté',
                            Tokens::TEXT_BODY_SMALL,
                            ($authUser !== null ? Color::green(600) : Tokens::inkMuted())->toHex(),
                        ),
                    ),
                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_XL),
                        new NativeCard(RenderFlex::column([
                            new RenderText('Compteur', Tokens::TEXT_CAPTION, Tokens::inkMuted()->toHex(), bold: true, letterSpacing: 0.04),
                            new RenderPadding(EdgeInsets::only(top: 4), new RenderText((string) $count, Tokens::TEXT_DISPLAY, Tokens::ink()->toHex(), bold: true)),
                            new RenderPadding(
                                EdgeInsets::only(top: Tokens::SPACE_LG),
                                new NativeButton('Incrémenter', 'home_increment', icon: 'add', width: $screenWidth - 2 * (Tokens::SPACE_XL + Tokens::SPACE_LG)),
                            ),
                        ]), elevation: 3),
                    ),
                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_XL),
                        new NativeListTile('Réglages', 'Préférences réelles', 'settings', trailingIcon: 'chevron_right', action: 'navigate:settings'),
                    ),
                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        new NativeListTile('Documents', 'Étape 3/4 — checklist', 'description', trailingIcon: 'chevron_right', action: 'navigate:documents'),
                    ),
                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        new NativeListTile('Vérification', 'Code OTP', 'shield', trailingIcon: 'chevron_right', action: 'navigate:otp'),
                    ),
                ], crossAxisAlignment: CrossAxisAlignment::STRETCH),
            ),
            width: $screenWidth,
            background: Tokens::surfaceMuted(),
        );
    }
}
