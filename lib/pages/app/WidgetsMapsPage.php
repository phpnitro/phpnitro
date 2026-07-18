<?php

namespace Engine\App;

use Engine\Column;
use Engine\Divider;
use Engine\Link;
use Engine\Maps\MapView;
use Engine\Screen;
use Engine\SingleScrollView;
use Engine\Text;
use Engine\Widget;

/**
 * MapView::make() auto-picks Mapbox > Google Maps > OpenStreetMap based on
 * which key is set in .env (same priority idiom as
 * CheckoutPage::selectPaymentWidget()) — this page always shows that
 * resolved choice, plus explains why Mapbox/Google can't be shown directly
 * without a real account (see phpnitro.yml's `maps:` section, `phpx maps`).
 */
final class WidgetsMapsPage extends Screen
{
    protected function initialState(): array
    {
        return [];
    }

    private function section(string $caption, Widget $example): Widget
    {
        return Column::make([
            Text::make($caption, 'text-sm text-gray-500 dark:text-gray-400'),
            $example,
            Divider::make(),
        ], 'flex flex-col gap-2');
    }

    public function build(): Widget
    {
        $configured = match (true) {
            ($_ENV['MAPBOX_ACCESS_TOKEN'] ?? '') !== '' => 'Mapbox (MAPBOX_ACCESS_TOKEN configuré)',
            ($_ENV['GOOGLE_MAPS_API_KEY'] ?? '') !== '' => 'Google Maps (GOOGLE_MAPS_API_KEY configuré)',
            default => 'OpenStreetMap (aucune clé configurée — repli par défaut)',
        };

        return SingleScrollView::make(Column::make([
            Text::make('Cartes', 'text-2xl font-bold text-gray-900 dark:text-gray-100'),
            Text::make(
                "Fournisseur actuellement résolu par MapView::make() : {$configured}. "
                . "Renseigne MAPBOX_ACCESS_TOKEN ou GOOGLE_MAPS_API_KEY dans .env pour voir un autre fournisseur ici.",
                'text-sm text-gray-500 dark:text-gray-400',
            ),
            $this->section('MapView::make() — résolution automatique', MapView::make(48.8566, 2.3522, 14)),

            Link::make('Retour à la vitrine', '/widgets'),
        ], 'flex flex-col gap-4 p-4'));
    }
}
