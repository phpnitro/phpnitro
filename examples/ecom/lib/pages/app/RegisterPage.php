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

final class RegisterPage extends Screen
{
    protected function initialState(): array
    {
        return ['error' => null];
    }

    public function showsBottomNav(): bool
    {
        return false;
    }

    /**
     * @param array<string, string> $data
     */
    protected function onRegister(array $data): ?string
    {
        $name = trim($data['name'] ?? '');
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
        $confirm = $data['password_confirm'] ?? '';

        if ($name === '' || $email === '' || $password === '') {
            $this->state['error'] = 'Tous les champs sont obligatoires.';

            return null;
        }

        if ($password !== $confirm) {
            $this->state['error'] = 'Les mots de passe ne correspondent pas.';

            return null;
        }

        $users = new UserRepository();

        if ($users->emailExists($email)) {
            $this->state['error'] = 'Cet email est déjà utilisé.';

            return null;
        }

        $userId = $users->create($name, $email, $password);
        $_SESSION['auth_user_id'] = $userId;

        return '/account';
    }

    public function build(): Widget
    {
        $children = [ErrorBanner::make($this->state['error'])];

        $children[] = Form::make([
            TextField::make('name', label: 'Nom'),
            TextField::make('email', label: 'Email', type: 'email'),
            TextField::make('password', label: 'Mot de passe', type: 'password'),
            TextField::make('password_confirm', label: 'Confirmer le mot de passe', type: 'password'),
            Button::make('Créer mon compte', classes: 'bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded-lg w-full'),
        ], action: 'register');

        $children[] = Link::make('Déjà inscrit ? Se connecter', '/login');

        return Scaffold::make(
            body: Column::make($children, 'flex flex-col gap-4 p-4'),
            appBar: AppBar::make('Créer un compte', backHref: '/account'),
        );
    }
}
