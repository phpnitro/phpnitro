<?php

namespace Engine\App;

use Backend\Repository\UserRepository;
use Engine\AppBar;
use Engine\Button;
use Engine\Column;
use Engine\ErrorBanner;
use Engine\Form;
use Engine\Link;
use Engine\Scaffold;
use Engine\Screen;
use Engine\TextField;
use Engine\Widget;

final class LoginPage extends Screen
{
    protected function initialState(): array
    {
        return ['error' => null];
    }

    /**
     * @param array<string, string> $data
     */
    protected function onLogin(array $data): ?string
    {
        $users = new UserRepository();
        $user = $users->findByEmail(trim($data['email'] ?? ''));

        if ($user === null || !$users->verifyPassword($user, $data['password'] ?? '')) {
            $this->state['error'] = 'Identifiants invalides.';

            return null;
        }

        $_SESSION['auth_user_id'] = $user['id'];

        return '/account';
    }

    public function build(): Widget
    {
        $children = [ErrorBanner::make($this->state['error'])];

        $children[] = Form::make([
            TextField::make('email', label: 'Email', type: 'email'),
            TextField::make('password', label: 'Mot de passe', type: 'password'),
            Button::make('Se connecter', classes: 'bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded-lg w-full'),
        ], action: 'login');

        $children[] = Link::make('Pas encore de compte ? Créer un compte', '/register');

        return Scaffold::make(
            body: Column::make($children, 'flex flex-col gap-4 p-4'),
            appBar: AppBar::make('Connexion', backHref: '/account'),
        );
    }
}
