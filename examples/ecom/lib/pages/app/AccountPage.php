<?php

namespace Engine\App;

use Backend\Repository\UserRepository;
use Engine\AppBar;
use Engine\BottomNavigation;
use Engine\Button;
use Engine\Color;
use Engine\Column;
use Engine\FontWeight;
use Engine\Link;
use Engine\Scaffold;
use Engine\Screen;
use Engine\Text;
use Engine\ThemeToggle;
use Engine\Widget;

final class AccountPage extends Screen
{
    protected function initialState(): array
    {
        return [];
    }

    protected function onLogout(): void
    {
        unset($_SESSION['auth_user_id']);
    }

    public function build(): Widget
    {
        $userId = $_SESSION['auth_user_id'] ?? null;

        if ($userId === null) {
            return Scaffold::make(
                body: Column::make([
                    Text::make('Connecte-toi pour voir ton compte.', color: Color::gray(600)),
                    Link::make('Se connecter', '/login', 'block text-center bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded-lg'),
                    Link::make('Créer un compte', '/register', 'block text-center bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100 font-medium px-4 py-2 rounded-lg'),
                ], 'flex flex-col gap-3 p-4'),
                appBar: AppBar::make('Compte'),
                bottomNavigation: BottomNavigation::make(AppNav::items()),
            );
        }

        $user = (new UserRepository())->find($userId);

        if ($user === null) {
            // Stale session (e.g. pointing at a user that no longer exists
            // in the database) — treat as logged out instead of crashing.
            unset($_SESSION['auth_user_id']);

            return Scaffold::make(
                body: Column::make([
                    Text::make('Session expirée, reconnecte-toi.', color: Color::gray(600)),
                    Link::make('Se connecter', '/login', 'block text-center bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded-lg'),
                    Link::make('Créer un compte', '/register', 'block text-center bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100 font-medium px-4 py-2 rounded-lg'),
                ], 'flex flex-col gap-3 p-4'),
                appBar: AppBar::make('Compte'),
                bottomNavigation: BottomNavigation::make(AppNav::items()),
            );
        }

        return Scaffold::make(
            body: Column::make([
                Text::make($user['name'], size: \Engine\TextSize::XL, weight: FontWeight::BOLD),
                Text::make($user['email'], color: Color::gray(600)),
                ThemeToggle::make(),
                Button::make('Se déconnecter', action: 'logout', classes: 'text-red-600 hover:underline text-left'),
            ], 'flex flex-col gap-3 p-4'),
            appBar: AppBar::make('Compte'),
            bottomNavigation: BottomNavigation::make(AppNav::items()),
        );
    }
}
