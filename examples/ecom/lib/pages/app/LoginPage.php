<?php

namespace Engine\App;

use Backend\Repository\UserRepository;
use Engine\AppBar;
use Engine\Button;
use Engine\Color;
use Engine\Column;
use Engine\Form;
use Engine\Link;
use Engine\Scaffold;
use Engine\Screen;
use Engine\Text;
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
        require_once dirname(__DIR__, 2) . '/backend/bootstrap.php';

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
        $children = [];

        if ($this->state['error'] !== null) {
            $children[] = Text::make($this->state['error'], color: Color::red(600));
        }

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
