<?php

namespace Engine\App;

use Engine\Native\CrossAxisAlignment;
use Engine\Native\EdgeInsets;
use Engine\Native\NativeAppBar;
use Engine\Native\NativeListTile;
use Engine\Native\NativeScaffold;
use Engine\Native\RenderContainer;
use Engine\Native\RenderFlex;
use Engine\Native\RenderNode;
use Engine\Native\RenderPadding;
use Engine\Native\RenderText;
use Engine\Native\Tokens;

/**
 * La conversion native de WidgetsIndexPage.php — un simple menu de
 * navigation, chaque Link::make() devient une NativeListTile. Les
 * catégories qui n'ont pas encore d'écran natif dédié restent absentes
 * du menu plutôt que de pointer vers une route qui n'existe pas.
 */
final class NativeWidgetsIndexScreen
{
    public static function build(float $screenWidth, float $screenHeight): RenderNode
    {
        $body = new RenderContainer(
            new RenderPadding(
                EdgeInsets::all(Tokens::SPACE_XL),
                RenderFlex::column([
                    new RenderText("Chaque catégorie montre les widgets natifs disponibles.", Tokens::TEXT_BODY_SMALL, Tokens::inkMuted()->toHex()),
                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_XL),
                        new NativeListTile('Mise en page', 'Align, Stack, Wrap, Table...', 'dashboard', trailingIcon: 'chevron_right', action: 'navigate:widgets-layout'),
                    ),
                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        new NativeListTile('Device', 'Vibreur, torche, batterie, notif...', 'smartphone', trailingIcon: 'chevron_right', action: 'navigate:device'),
                    ),
                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        new NativeListTile('Formulaires', 'SelectBox, DatePicker, dialogues...', 'edit_note', trailingIcon: 'chevron_right', action: 'navigate:widgets-forms'),
                    ),
                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        new NativeListTile('Boîtes de dialogue', 'Alert/Confirm — AlertDialog réel', 'chat_bubble', trailingIcon: 'chevron_right', action: 'navigate:widgets-dialogs'),
                    ),
                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        new NativeListTile('Stepper', 'Assistant multi-étapes', 'linear_scale', trailingIcon: 'chevron_right', action: 'navigate:widgets-stepper'),
                    ),
                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        new NativeListTile('Countries', '194 pays, offline', 'public', trailingIcon: 'chevron_right', action: 'navigate:widgets-countries'),
                    ),
                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        new NativeListTile('Média', 'AudioPlayer — MediaPlayer réel', 'music_note', trailingIcon: 'chevron_right', action: 'navigate:widgets-media'),
                    ),
                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        new NativeListTile('Cartes', 'Tuile OpenStreetMap réelle', 'map', trailingIcon: 'chevron_right', action: 'navigate:widgets-maps'),
                    ),
                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        new NativeListTile('Firebase Auth', 'Appel REST réel', 'local_fire_department', trailingIcon: 'chevron_right', action: 'navigate:widgets-firebase-auth'),
                    ),
                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        new NativeListTile('Backend PHP', 'Appel API en-process', 'api', trailingIcon: 'chevron_right', action: 'navigate:api'),
                    ),
                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        new NativeListTile('Liste virtualisée', '5000 lignes, rendu paresseux réel', 'view_list', trailingIcon: 'chevron_right', action: 'navigate:widgets-lazylist'),
                    ),
                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        new NativeListTile('Tâches', 'Glisser pour supprimer — geste continu réel', 'swipe', trailingIcon: 'chevron_right', action: 'navigate:widgets-dismissible'),
                    ),
                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        new NativeListTile('Étapes', 'Appui long + glisser pour réordonner', 'drag_indicator', trailingIcon: 'chevron_right', action: 'navigate:widgets-reorder'),
                    ),
                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        new NativeListTile('Lottie', 'Animation en boucle — LottieAnimationView réel', 'animation', trailingIcon: 'chevron_right', action: 'navigate:widgets-lottie'),
                    ),
                    new RenderPadding(
                        EdgeInsets::only(top: Tokens::SPACE_MD),
                        new NativeListTile('Onglets (état client)', 'Changer d\'onglet — zéro aller-retour serveur', 'tab', trailingIcon: 'chevron_right', action: 'navigate:widgets-clienttabs'),
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
            appBar: new NativeAppBar($screenWidth, 'Vitrine des widgets', backAction: 'back'),
            bottomNav: NativeAppShell::bottomNav($screenWidth, 'widgets'),
        );
    }
}
