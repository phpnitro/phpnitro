<?php

namespace Engine\App;

use Engine\BottomNavigation;
use Engine\Button;
use Engine\Color;
use Engine\Column;
use Engine\FloatingActionButton;
use Engine\FontWeight;
use Engine\GestureDetector;
use Engine\Link;
use Engine\Screen;
use Engine\Text;
use Engine\TextSize;
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
            Text::make('Mon application', size: TextSize::XL2, weight: FontWeight::BOLD, color: Color::gray(900)),
            GestureDetector::make(
                Text::make('Compteur : ' . $this->state['count'] . ' (double-clic ou swipe)'),
                onDoubleClick: 'increment',
                onSwipeLeft: 'decrement',
                onSwipeRight: 'increment',
            ),
            Button::make('Incrémenter', action: 'increment'),
            Link::make('Réglages', '/settings'),
            Link::make('Produit #42', '/product/42'),
            ThemeToggle::make(),
            FloatingActionButton::make('+', action: 'increment'),
            BottomNavigation::make(AppNav::items(), variant: BottomNavigation::VARIANT_PILLS),
        ]);
    }
}
