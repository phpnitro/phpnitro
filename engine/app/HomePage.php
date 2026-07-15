<?php

namespace Engine\App;

use Engine\Button;
use Engine\Column;
use Engine\Link;
use Engine\Screen;
use Engine\Text;
use Engine\Widget;

final class HomePage extends Screen
{
    protected function initialState(): array
    {
        return ['count' => 0];
    }

    protected function onIncrement(): void
    {
        $this->state['count']++;
    }

    public function build(): Widget
    {
        return Column::make([
            Text::make('Mon application', 'text-2xl font-bold text-gray-900'),
            Text::make('Compteur : ' . $this->state['count']),
            Button::make('Incrémenter', action: 'increment'),
            Link::make('Réglages', '/settings'),
        ]);
    }
}
