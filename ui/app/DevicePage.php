<?php

namespace Engine\App;

use Engine\BottomNavigation;
use Engine\CameraPreview;
use Engine\Column;
use Engine\Link;
use Engine\LocationButton;
use Engine\MicrophoneButton;
use Engine\Screen;
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
        return Column::make([
            Text::make('Capacités du device', 'text-2xl font-bold text-gray-900 dark:text-gray-100'),
            VibrateButton::make(),
            LocationButton::make(),
            MicrophoneButton::make(),
            CameraPreview::make(),
            Link::make("Retour à l'accueil", '/'),
            BottomNavigation::make(AppNav::items(), variant: BottomNavigation::VARIANT_PILLS),
        ]);
    }
}
