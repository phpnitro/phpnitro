<?php

namespace Engine\App;

use Engine\BottomNavigation;
use Engine\CameraPreview;
use Engine\Column;
use Engine\FingerprintButton;
use Engine\Link;
use Engine\LocationButton;
use Engine\MapView;
use Engine\MicrophoneButton;
use Engine\Screen;
use Engine\SingleScrollView;
use Engine\StreamBuilder;
use Engine\Text;
use Engine\VibrateButton;
use Engine\Widget;

final class DevicePage extends Screen
{
    protected function initialState(): array
    {
        return [];
    }

    public function build(): Widget
    {
        return SingleScrollView::make(Column::make([
            Text::make('Capacités du device', 'text-2xl font-bold text-gray-900 dark:text-gray-100'),
            VibrateButton::make(),
            LocationButton::make(),
            MicrophoneButton::make(),
            CameraPreview::make(),
            FingerprintButton::make(),
            Text::make('Carte (OpenStreetMap, zéro clé API)', 'text-lg font-semibold text-gray-900 dark:text-gray-100'),
            MapView::make(48.8566, 2.3522),
            Text::make('Contenu live (StreamBuilder, polling toutes les 2s)', 'text-lg font-semibold text-gray-900 dark:text-gray-100'),
            StreamBuilder::make('/fragment/server-time', Text::make('Chargement...')),
            Link::make("Retour à l'accueil", '/'),
            BottomNavigation::make(AppNav::items(), variant: BottomNavigation::VARIANT_PILLS),
        ], 'flex flex-col gap-4 p-4'));
    }
}
