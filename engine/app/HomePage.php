<?php

namespace Engine\App;

use Engine\BottomNavigation;
use Engine\Button;
use Engine\Column;
use Engine\FloatingActionButton;
use Engine\GestureDetector;
use Engine\Link;
use Engine\Screen;
use Engine\Text;
use Engine\ThemeToggle;
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

    protected function onDecrement(): void
    {
        $this->state['count']--;
    }

    public function build(): Widget
    {
        return Column::make([
            Text::make('Mon application', 'text-2xl font-bold text-gray-900 dark:text-gray-100'),
            GestureDetector::make(
                Text::make('Compteur : ' . $this->state['count'] . ' (double-clic ou swipe)'),
                onDoubleClick: 'increment',
                onSwipeLeft: 'decrement',
                onSwipeRight: 'increment',
            ),
            Button::make('Incrémenter', action: 'increment'),
            Link::make('Réglages', '/settings'),
            ThemeToggle::make(),
            FloatingActionButton::make('+', action: 'increment'),
            BottomNavigation::make([
                ['label' => 'Accueil', 'href' => '/'],
                ['label' => 'Réglages', 'href' => '/settings'],
                ['label' => 'Device', 'href' => '/device'],
            ]),
        ]);
    }
}
