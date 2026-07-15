<?php

namespace Engine\App;

use Engine\Column;
use Engine\Link;
use Engine\Screen;
use Engine\Text;
use Engine\Widget;

final class SettingsPage extends Screen
{
    protected function initialState(): array
    {
        return [];
    }

    public function build(): Widget
    {
        return Column::make([
            Text::make('Réglages', 'text-2xl font-bold text-gray-900'),
            Link::make("Retour à l'accueil", '/'),
        ]);
    }
}
