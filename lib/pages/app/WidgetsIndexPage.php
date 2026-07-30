<?php

namespace Engine\App;

use Engine\Button;
use Engine\Column;
use Engine\Link;
use Engine\Screen;
use Engine\SingleScrollView;
use Engine\Text;
use Engine\Widget;

/**
 * Every widget in packages/ui/src (plus the Maps/Dialogs packages)
 * demonstrated somewhere, so "does it actually work" is a visual check on
 * a real device instead of a promise. Payment widgets (Engine\Payments\)
 * used to be demonstrated in examples/ecom's live checkout, which was
 * removed once native became the app's real rendering engine — needing
 * a real gateway key to render meaningfully, they were never duplicated
 * here without one.
 */
final class WidgetsIndexPage extends Screen
{
    protected function initialState(): array
    {
        return [];
    }

    public function build(): Widget
    {
        return SingleScrollView::make(Column::make([
            Text::make('Vitrine des widgets', 'text-2xl font-bold text-gray-900 dark:text-gray-100'),
            Text::make(
                "Chaque catégorie ci-dessous montre tous les widgets qui n'apparaissent pas déjà ailleurs dans cette démo.",
                'text-sm text-gray-500 dark:text-gray-400',
            ),
            Link::make('Mise en page (Align, Center, Container, Row...)', '/widgets/layout'),
            Link::make('Formulaires (DatePicker, SelectBox, ProgressBar...)', '/widgets/forms'),
            Link::make('Capacités device (vibreur, son, notif, empreinte...)', '/device'),
            // Boîtes de dialogue / Stepper / Countries / Média / Cartes :
            // leurs pages WebView ont été retirées une fois leur
            // conversion native à parité complète (voir
            // lib/pages/app/NativeWidgets*Screen.php) — ces boutons
            // ouvrent l'écran natif qui les remplace.
            Button::make("Boîtes de dialogue (Engine\\Dialogs\\)", onClick: "phpxDevice.openNativeRenderPreviewAt('widgets-dialogs')", classes: 'text-blue-600 hover:underline text-left'),
            Button::make('Stepper (assistant multi-étapes)', onClick: "phpxDevice.openNativeRenderPreviewAt('widgets-stepper')", classes: 'text-blue-600 hover:underline text-left'),
            Button::make('Countries (Engine\\Countries\\, offline, 194 pays)', onClick: "phpxDevice.openNativeRenderPreviewAt('widgets-countries')", classes: 'text-blue-600 hover:underline text-left'),
            Button::make("Firebase Auth (Engine\\Firebase\\)", onClick: "phpxDevice.openNativeRenderPreviewAt('widgets-firebase-auth')", classes: 'text-blue-600 hover:underline text-left'),
            Button::make('Média (AudioPlayer, VideoPlayer, GoogleTranslate...)', onClick: "phpxDevice.openNativeRenderPreviewAt('widgets-media')", classes: 'text-blue-600 hover:underline text-left'),
            Button::make('Cartes (osmdroid, pan/zoom réel)', onClick: "phpxDevice.openNativeRenderPreviewAt('widgets-maps')", classes: 'text-blue-600 hover:underline text-left'),
            Text::make(
                "Paiement (Engine\\Payments\\) : 7 gateways réels — voir packages/payments/, ils ont besoin d'une vraie clé pour s'afficher.",
                'text-sm text-gray-500 dark:text-gray-400',
            ),
            // '/' has no WebView route anymore — see ProductPage.php's
            // same fix.
            Button::make("Retour à l'accueil", onClick: "phpxDevice.openNativeRenderPreviewAt('home')", classes: 'text-blue-600 hover:underline text-left'),
        ], 'flex flex-col gap-4 p-4'));
    }
}
