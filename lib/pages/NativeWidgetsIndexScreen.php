<?php

namespace Engine\App;

use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\AppBar;
use Engine\Native\BottomNavigation;
use Engine\Native\ListTile;
use Engine\Native\NestedScroll;
use Engine\Native\Scaffold;
use Engine\Native\Container;
use Engine\Native\Flex;
use Engine\Native\Widget;
use Engine\Native\Padding;
use Engine\Native\Text;
use Engine\Native\Tokens;

/**
 * La conversion native de WidgetsIndexPage.php — un simple menu de
 * navigation, chaque Link::make() devient une ListTile. Les
 * catégories qui n'ont pas encore d'écran natif dédié restent absentes
 * du menu plutôt que de pointer vers une route qui n'existe pas.
 */
final class NativeWidgetsIndexScreen
{
    public static function build(float $screenWidth, float $screenHeight): Widget
    {
        // Real bug found the first time this screen ever ran tall enough
        // to matter (a physical Mac, PhpNitroMacApp — a resizable desktop
        // window, unlike a phone's fixed-height screen): with 19 rows and
        // no bounded viewport, this body's real height (~1450px) far
        // exceeds any reasonable window/screen height. The page-level
        // hit-test (see rust/phpnitro-render/src/hittest.rs's own
        // top-level `hitRegions[]` loop, or NativeCanvasView.kt's
        // identical `handleTap()`) never bounds-checks a region against
        // what's actually visible — only `vScroll`'s own branch does
        // that (`tap_x < vx || ... || touch_y > vy + vh { continue }`) —
        // so an OFF-SCREEN row (never painted, since the renderer's own
        // output buffer is exactly `bounds.height` tall) still WINS a tap
        // at whatever screen coordinate its own unclipped, un-scrolled
        // absolute position happens to fall on — confirmed for real:
        // tapping the bottom nav's "Device" button hit the "Stepper" row
        // instead, because that row's absolute y-range happened to
        // overlap the nav bar's fixed screen position. `NestedScroll`
        // (already proven in NativeWidgetsFormsScreen.php's own small
        // demo list) gives this list a real, bounded viewport instead —
        // its own `vScroll` branch DOES exclude anything outside that
        // viewport from hit-testing.
        // Minus the Padding::all(SPACE_XL) wrapping the NestedScroll
        // below too — that padding shrinks its actual available height
        // by 2×SPACE_XL, not just its width.
        $viewportHeight = $screenHeight - AppBar::HEIGHT - BottomNavigation::HEIGHT - 2 * Tokens::SPACE_XL;

        $body = new Container(
            new Padding(
                EdgeInsets::all(Tokens::SPACE_XL),
                new NestedScroll('widgets-index-list', Flex::column([
                    new Text("Chaque catégorie montre les widgets natifs disponibles.", Tokens::TEXT_BODY_SMALL, Tokens::inkMuted()->toHex()),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_XL),
                        new ListTile('Mise en page', 'Align, Stack, Wrap, Table...', 'dashboard', trailingIcon: 'chevron_right', action: 'navigate:widgets-layout'),
                    ),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        new ListTile('Device', 'Vibreur, torche, batterie, notif...', 'smartphone', trailingIcon: 'chevron_right', action: 'navigate:device'),
                    ),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        new ListTile('Formulaires', 'SelectBox, DatePicker, dialogues...', 'edit_note', trailingIcon: 'chevron_right', action: 'navigate:widgets-forms'),
                    ),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        new ListTile('Boîtes de dialogue', 'Alert/Confirm — AlertDialog réel', 'chat_bubble', trailingIcon: 'chevron_right', action: 'navigate:widgets-dialogs'),
                    ),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        new ListTile('Stepper', 'Assistant multi-étapes', 'linear_scale', trailingIcon: 'chevron_right', action: 'navigate:widgets-stepper'),
                    ),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        new ListTile('Countries', '194 pays, offline', 'public', trailingIcon: 'chevron_right', action: 'navigate:widgets-countries'),
                    ),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        new ListTile('Média', 'AudioPlayer — MediaPlayer réel', 'music_note', trailingIcon: 'chevron_right', action: 'navigate:widgets-media'),
                    ),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        new ListTile('Cartes', 'Tuile OpenStreetMap réelle', 'map', trailingIcon: 'chevron_right', action: 'navigate:widgets-maps'),
                    ),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        new ListTile('Firebase Auth', 'Appel REST réel', 'local_fire_department', trailingIcon: 'chevron_right', action: 'navigate:widgets-firebase-auth'),
                    ),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        new ListTile('Paiements', 'Feexpay mobile money — appel REST réel', 'payments', trailingIcon: 'chevron_right', action: 'navigate:widgets-payments'),
                    ),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        new ListTile('Backend PHP', 'Appel API en-process', 'api', trailingIcon: 'chevron_right', action: 'navigate:api'),
                    ),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        new ListTile('Liste virtualisée', '5000 lignes, rendu paresseux réel', 'view_list', trailingIcon: 'chevron_right', action: 'navigate:widgets-lazylist'),
                    ),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        new ListTile('Tâches', 'Glisser pour supprimer — geste continu réel', 'swipe', trailingIcon: 'chevron_right', action: 'navigate:widgets-dismissible'),
                    ),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        new ListTile('Étapes', 'Appui long + glisser pour réordonner', 'drag_indicator', trailingIcon: 'chevron_right', action: 'navigate:widgets-reorder'),
                    ),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        new ListTile('Lottie', 'Animation en boucle — LottieAnimationView réel', 'animation', trailingIcon: 'chevron_right', action: 'navigate:widgets-lottie'),
                    ),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        new ListTile('Onglets (état client)', 'Changer d\'onglet — zéro aller-retour serveur', 'tab', trailingIcon: 'chevron_right', action: 'navigate:widgets-clienttabs'),
                    ),
                    new Padding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        new ListTile('Async (Isolates)', 'Calcul lourd dans un vrai processus séparé', 'bolt', trailingIcon: 'chevron_right', action: 'navigate:widgets-async'),
                    ),
                ], crossAxisAlignment: CrossAxisAlignment::STRETCH), $viewportHeight),
            ),
            width: $screenWidth,
            background: Tokens::surfaceMuted(),
        );

        return new Scaffold(
            $body,
            $screenWidth,
            $screenHeight,
            appBar: new AppBar($screenWidth, 'Vitrine des widgets', backAction: 'back'),
            bottomNav: NativeAppShell::bottomNav($screenWidth, 'widgets'),
        );
    }
}
