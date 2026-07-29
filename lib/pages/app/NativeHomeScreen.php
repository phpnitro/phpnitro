<?php

namespace Engine\App;

use Engine\Color;
use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\NativeAppBar;
use Engine\Native\NativeBottomNavigation;
use Engine\Native\NativeButton;
use Engine\Native\NativeCard;
use Engine\Native\NativeListTile;
use Engine\Native\NativeScaffold;
use Engine\Native\RenderContainer;
use Engine\Native\RenderFlex;
use Engine\Native\RenderNode;
use Engine\Native\RenderPadding;
use Engine\Native\RenderTappable;
use Engine\Native\RenderText;
use Engine\Native\Tokens;
use Engine\Preferences\Preferences;

/**
 * The native conversion of HomePage.php (the real app's actual landing
 * screen, not another reference-image recreation) — same real session
 * auth check, same real persisted counter (via Engine\Preferences\,
 * same primitive NativeSettingsScreen already reads/writes), same
 * navigation targets. Now built on NativeScaffold — a real pinned AppBar
 * and BottomNavigation (see their docblocks) instead of every screen
 * hand-rolling its own header row.
 *
 * What's deliberately NOT carried over 1:1 from HomePage.php:
 * - GestureDetector's double-click/swipe interactions collapse to a
 *   single tap — this engine's hit-testing is tap-only for now, no
 *   gesture disambiguation beyond scroll-vs-tap.
 * - The Drawer (slide-in side panel) has no native equivalent yet; its
 *   destinations are reachable as plain NativeListTile rows instead.
 *
 * Root of the native screen stack — NativeRenderPocActivity starts here
 * by default; Documents/OTP/Settings are all one "back" away from it.
 */
final class NativeHomeScreen
{
    public static function build(float $screenWidth, float $screenHeight): RenderNode
    {
        $authUser = $_SESSION['auth_user'] ?? null;
        // A separate Preferences key from NativeDocumentsScreen's
        // file-backed tap count — same real round-trip demonstration,
        // deliberately not the same counter (they mean different things
        // and shouldn't share state just because both are "a number that
        // goes up").
        $count = (int) Preferences::get('native_home_counter', '0');

        $body = new RenderContainer(
            new RenderPadding(
                EdgeInsets::all(Tokens::SPACE_XL),
                RenderFlex::column([
                    $authUser !== null
                        ? new RenderTappable(
                            new RenderText("Connecté : {$authUser} — se déconnecter", Tokens::TEXT_BODY_SMALL, Color::green(600)->toHex(), bold: true),
                            'logout',
                        )
                        : new RenderTappable(
                            new RenderText('Non connecté — se connecter', Tokens::TEXT_BODY_SMALL, Tokens::inkSecondary()->toHex(), bold: true),
                            'navigate:login',
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
                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        new NativeListTile('Produit #42', 'Route param réel', 'inventory_2', trailingIcon: 'chevron_right', action: 'navigate:product/42'),
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
            appBar: new NativeAppBar($screenWidth, 'Mon application'),
            bottomNav: NativeAppShell::bottomNav($screenWidth, 'home'),
        );
    }
}
