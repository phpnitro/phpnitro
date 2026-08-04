<?php

namespace Engine\App;

use Engine\Color;
use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\AppBar;
use Engine\Native\BottomNavigation;
use Engine\Native\Button;
use Engine\Native\Card;
use Engine\Native\Drawer;
use Engine\Native\Fab;
use Engine\Native\GestureDetector;
use Engine\Native\IconCircle;
use Engine\Native\ListTile;
use Engine\Native\Scaffold;
use Engine\Native\Container;
use Engine\Native\Flex;
use Engine\Native\Widget;
use Engine\Native\Padding;
use Engine\Native\Tappable;
use Engine\Native\Text;
use Engine\Native\Tokens;
use Engine\Preferences\Preferences;

/**
 * The native conversion of HomePage.php (the real app's actual landing
 * screen, not another reference-image recreation) — same real session
 * auth check, same real persisted counter (via Engine\Preferences\,
 * same primitive NativeSettingsScreen already reads/writes), same
 * navigation targets. Now built on Scaffold — a real pinned AppBar
 * and BottomNavigation (see their docblocks) instead of every screen
 * hand-rolling its own header row.
 *
 * Full parity with HomePage.php as of this pass: Drawer, FloatingActionButton
 * and GestureDetector (double-tap/swipe on the counter, via a real
 * android.view.GestureDetector — see NativeCanvasView.kt) are all real now,
 * not approximated. The AppBar's leading hamburger toggles
 * $_GET['drawer_open'] the same "server-known open/close flag" way every
 * other stateful native widget works (see Drawer's docblock).
 *
 * Root of the native screen stack — NativeRenderPocActivity starts here
 * by default; Documents/OTP/Settings are all one "back" away from it.
 */
final class NativeHomeScreen
{
    public static function build(float $screenWidth, float $screenHeight): Widget
    {
        $authUser = $_SESSION['auth_user'] ?? null;
        // A separate Preferences key from NativeDocumentsScreen's
        // file-backed tap count — same real round-trip demonstration,
        // deliberately not the same counter (they mean different things
        // and shouldn't share state just because both are "a number that
        // goes up").
        $count = (int) Preferences::get('native_home_counter', '0');
        $drawerOpen = ($_GET['drawer_open'] ?? '') === '1';

        $body = new Container(
            new Padding(
                EdgeInsets::all(Tokens::SPACE_XL),
                Flex::column([
                    $authUser !== null
                        ? new Tappable(
                            new Text("Connecté : {$authUser} — se déconnecter", Tokens::TEXT_BODY_SMALL, Color::green(600)->toHex(), bold: true),
                            'logout',
                        )
                        : new Tappable(
                            new Text('Non connecté — se connecter', Tokens::TEXT_BODY_SMALL, Tokens::inkSecondary()->toHex(), bold: true),
                            'navigate:login',
                        ),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_XL),
                        new Card(Flex::column([
                            new Text('Compteur', Tokens::TEXT_CAPTION, Tokens::inkMuted()->toHex(), bold: true, letterSpacing: 0.04),
                            new Padding(
                                EdgeInsets::only(top: 4),
                                new GestureDetector(
                                    new Text((string) $count, Tokens::TEXT_DISPLAY, Tokens::ink()->toHex(), bold: true),
                                    onDoubleClick: 'home_increment',
                                    onSwipeLeft: 'home_decrement',
                                    onSwipeRight: 'home_increment',
                                ),
                            ),
                            new Padding(
                                EdgeInsets::only(top: Tokens::SPACE_LG),
                                new Button('Incrémenter', 'home_increment', icon: 'add', width: $screenWidth - 2 * (Tokens::SPACE_XL + Tokens::SPACE_LG)),
                            ),
                        ]), elevation: 3),
                    ),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_XL),
                        new ListTile('Réglages', 'Préférences réelles', 'settings', trailingIcon: 'chevron_right', action: 'navigate:settings'),
                    ),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        new ListTile('Documents', 'Étape 3/4 — checklist', 'description', trailingIcon: 'chevron_right', action: 'navigate:documents'),
                    ),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        new ListTile('Vérification', 'Code OTP', 'shield', trailingIcon: 'chevron_right', action: 'navigate:otp'),
                    ),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        new ListTile('Produit #42', 'Route multi-paramètres réelle', 'inventory_2', trailingIcon: 'chevron_right', action: 'navigate:product?id=42&tab=reviews'),
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
            appBar: new AppBar(
                $screenWidth,
                'Mon application',
                leading: new IconCircle('menu', 36.0, action: 'toggle:drawer_open', meta: ['next' => '1']),
            ),
            bottomNav: NativeAppShell::bottomNav($screenWidth, 'home'),
            fab: new Fab('add', 'home_increment'),
            drawer: $drawerOpen ? new Drawer($screenWidth, $screenHeight, [
                ['label' => 'Accueil', 'icon' => 'home', 'action' => 'tab:home'],
                ['label' => 'Réglages', 'icon' => 'settings', 'action' => 'navigate:settings'],
                ['label' => 'Device', 'icon' => 'smartphone', 'action' => 'navigate:device'],
                ['label' => 'API', 'icon' => 'api', 'action' => 'navigate:api'],
                ['label' => 'Widgets', 'icon' => 'widgets', 'action' => 'navigate:widgets'],
            ], 'Mon application') : null,
        );
    }
}
