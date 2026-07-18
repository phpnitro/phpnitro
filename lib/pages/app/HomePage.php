<?php

namespace Engine\App;

use Engine\AppBar;
use Engine\Button;
use Engine\Color;
use Engine\Column;
use Engine\Drawer;
use Engine\DrawerToggle;
use Engine\Dropdown;
use Engine\Flash;
use Engine\FlashMessage;
use Engine\FloatingActionButton;
use Engine\FontWeight;
use Engine\GestureDetector;
use Engine\Link;
use Engine\ListView;
use Engine\Scaffold;
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
        Flash::set('Compteur incrémenté !');
    }

    protected function onDecrement(): void
    {
        $this->state['count']--;
    }

    protected function onLogout(): void
    {
        unset($_SESSION['auth_user']);
    }

    public function build(): Widget
    {
        $user = $_SESSION['auth_user'] ?? null;

        $authRow = $user !== null
            ? Column::make([
                Text::make("Connecté : {$user}", color: Color::green(600), weight: FontWeight::MEDIUM),
                Button::make('Se déconnecter', action: 'logout', classes: 'text-sm text-red-600 hover:underline text-left'),
            ], 'flex flex-col gap-1')
            : Link::make('Se connecter', '/login');

        return Scaffold::make(
            body: Column::make([
                FlashMessage::make(),
                $authRow,
                GestureDetector::make(
                    Text::make('Compteur : ' . $this->state['count'] . ' (double-clic ou swipe)'),
                    onDoubleClick: 'increment',
                    onSwipeLeft: 'decrement',
                    onSwipeRight: 'increment',
                ),
                Button::make('Incrémenter', action: 'increment'),
                Dropdown::make('Trier', [
                    ['label' => 'Plus récent', 'href' => '/?sort=recent'],
                    ['label' => 'Alphabétique', 'href' => '/?sort=alpha'],
                ]),
                ListView::make([
                    Link::make('Réglages', '/settings'),
                    Link::make('Produit #42', '/product/42'),
                    Link::make('Vitrine des widgets', '/widgets'),
                    ThemeToggle::make(),
                ]),
            ], 'flex flex-col gap-4'),
            appBar: AppBar::make('Mon application', leading: DrawerToggle::make()),
            hasBottomNav: true,
            floatingActionButton: FloatingActionButton::make('+', action: 'increment'),
            drawer: Drawer::make([
                ['label' => 'Accueil', 'href' => '/'],
                ['label' => 'Réglages', 'href' => '/settings'],
                ['label' => 'Device', 'href' => '/device'],
                ['label' => 'API', 'href' => '/api'],
                ['label' => 'Widgets', 'href' => '/widgets'],
            ], title: 'Mon application'),
        );
    }
}
