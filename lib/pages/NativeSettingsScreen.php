<?php

namespace Engine\App;

use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\Flexible;
use Engine\Native\AppBar;
use Engine\Native\ListTile;
use Engine\Native\Scaffold;
use Engine\Native\Container;
use Engine\Native\Flex;
use Engine\Native\Image;
use Engine\Native\Widget;
use Engine\Native\Padding;
use Engine\Native\Text;
use Engine\Native\Tokens;
use Engine\Preferences\Preferences;

/**
 * Point 5 of the "grow the framework" pass: not another synthetic mockup
 * screen — this one reads AND writes the SAME Engine\Preferences\ values
 * SettingsPage.php (the real, WebView-rendered settings screen) reads and
 * writes, proving the native pipeline can serve a genuinely data-backed
 * screen, not just a static reference-image recreation. Full parity now:
 * the accent color tile opens a real select dialog (persisted via
 * public/index.php's handling of $_GET['accent_choice'], the same
 * "action carries a value, PHP persists it" idiom NativeHomeScreen's
 * counter uses), the native-preview tile toggles Preferences the same way
 * SettingsPage::onToggleNativePreview() does, and the connectivity row
 * shows a real ConnectivityManager reading — refreshed automatically
 * while this screen is open via a registered NetworkCallback (see
 * NativeRenderPocActivity's connectivityCallback), not JS polling.
 *
 * Built from the IconCircle/ListTile widget layer instead of
 * hand-rolled Container/Center/Icon composition —
 * compare to git history before this refactor if you want to see what
 * that looked like duplicated across three screens. AppBar-only (no
 * BottomNavigation) — this is a secondary screen reached by
 * drilling in, not one of the app's tab-bar destinations.
 */
final class NativeSettingsScreen
{
    public static function build(float $screenWidth, float $screenHeight): Widget
    {
        $accent = Preferences::get('accent_color', 'blue');
        $nativePreviewEnabled = Preferences::get('native_render_preview_enabled', '0') === '1';
        $online = ($_GET['online'] ?? '1') === '1';

        $accentLabels = ['blue' => 'Bleu', 'purple' => 'Violet', 'emerald' => 'Émeraude'];

        $body = new Container(
            new Padding(
                EdgeInsets::all(Tokens::SPACE_XL),
                Flex::column([
                    Flex::row([
                        new Image(
                            'https://picsum.photos/200',
                            64,
                            64,
                            radius: 32,
                        ),
                        new Flexible(new Padding(EdgeInsets::only(left: Tokens::SPACE_MD), Flex::column([
                            new Text('Réglages natifs', Tokens::TEXT_TITLE, Tokens::ink()->toHex(), bold: true),
                            new Padding(
                                EdgeInsets::only(top: 2),
                                new Text('Données réelles — Engine\\Preferences\\', Tokens::TEXT_CAPTION, Tokens::inkMuted()->toHex()),
                            ),
                        ]))),
                    ], crossAxisAlignment: CrossAxisAlignment::CENTER),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_XL),
                        new ListTile(
                            "Couleur d'accent",
                            null,
                            'palette',
                            leadingColor: Tokens::inkSecondary(),
                            trailingText: $accentLabels[$accent] ?? $accent,
                            action: 'select:accent_choice',
                            meta: ['options' => $accentLabels, 'selected' => $accent],
                        ),
                    ),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        new ListTile(
                            'Rendu natif',
                            null,
                            'bolt',
                            leadingColor: Tokens::inkSecondary(),
                            trailingText: $nativePreviewEnabled ? 'Activé' : 'Désactivé',
                            action: 'togglenativepreview',
                        ),
                    ),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        new ListTile(
                            'Réseau',
                            null,
                            $online ? 'wifi' : 'wifi_off',
                            leadingColor: $online ? Tokens::success() : Tokens::danger(),
                            trailingText: $online ? 'En ligne' : 'Hors ligne',
                        ),
                    ),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        new ListTile('Moteur', null, 'memory', leadingColor: Tokens::inkSecondary(), trailingText: 'PHP -> Canvas'),
                    ),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_XL),
                        new Text('SUPPORT', Tokens::TEXT_CAPTION, Tokens::inkMuted()->toHex(), bold: true, letterSpacing: 0.04),
                    ),
                    // CrashReporter.kt persists every uncaught native
                    // exception AND every PHP error the JSON overlay ever
                    // showed — this just opens the share sheet on that
                    // log. Works in a real release build too (no
                    // isDebuggable() gate, unlike the dev tools overlay):
                    // this is for an actual user hitting an actual crash,
                    // not a developer's own device.
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        new ListTile(
                            'Signaler un problème',
                            'Envoie les derniers rapports de plantage enregistrés',
                            'bug_report',
                            leadingColor: Tokens::inkSecondary(),
                            action: 'device:report_crash',
                        ),
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
            appBar: new AppBar($screenWidth, 'Réglages', backAction: 'back'),
        );
    }
}
