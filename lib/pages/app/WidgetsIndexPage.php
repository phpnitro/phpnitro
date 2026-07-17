<?php

namespace Engine\App;

use Engine\BottomNavigation;
use Engine\Column;
use Engine\Link;
use Engine\Screen;
use Engine\SingleScrollView;
use Engine\Text;
use Engine\Widget;

/**
 * Every widget in packages/ui/src (plus the Maps/Dialogs/Payments service
 * packages) demonstrated somewhere, so "does it actually work" is a visual
 * check on a real device instead of a promise. Payment widgets need a real
 * gateway key to render meaningfully, so they're demonstrated in
 * examples/ecom's live checkout instead of duplicated here without keys.
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
            Link::make('Média (AudioPlayer, VideoPlayer, GoogleTranslate...)', '/widgets/media'),
            Link::make('Capacités device (vibreur, son, notif, empreinte...)', '/device'),
            Link::make('Cartes (Mapbox, Google Maps, OpenStreetMap)', '/widgets/maps'),
            Link::make('Boîtes de dialogue (Engine\\Dialogs\\)', '/widgets/dialogs'),
            Link::make('Stepper (assistant multi-étapes)', '/widgets/stepper'),
            Link::make('Firebase Auth (Engine\\Firebase\\)', '/widgets/firebase-auth'),
            Text::make(
                'Paiement (Engine\\Payments\\) : 7 gateways réels, testés en conditions réelles '
                . "dans examples/ecom/checkout — pas dupliqués ici, ils ont besoin d'une vraie clé pour s'afficher.",
                'text-sm text-gray-500 dark:text-gray-400',
            ),
            Link::make("Retour à l'accueil", '/'),
            BottomNavigation::make(AppNav::items(), variant: BottomNavigation::VARIANT_PILLS),
        ], 'flex flex-col gap-4 p-4'));
    }
}
