<?php

namespace Engine\App;

use Engine\AppBar;
use Engine\Button;
use Engine\Checkbox;
use Engine\Color;
use Engine\Column;
use Engine\Form;
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
        if (($data['username'] ?? '') === 'demo' && ($data['password'] ?? '') === 'demo') {
            $_SESSION['auth_user'] = $data['username'];
            $this->state['error'] = null;

            return '/';
        }

        $this->state['error'] = 'Identifiants invalides (essaie demo / demo).';

        return null;
    }

    public function build(): Widget
    {
        $children = [];

        if ($this->state['error'] !== null) {
            $children[] = Text::make($this->state['error'], color: Color::red(600));
        }

        $children[] = Form::make([
            TextField::make('username', label: 'Utilisateur', placeholder: 'demo'),
            TextField::make('password', label: 'Mot de passe', type: 'password'),
            Checkbox::make('remember', 'Se souvenir de moi'),
            Button::make('Se connecter'),
        ], action: 'login');

        return Scaffold::make(
            body: Column::make($children, 'flex flex-col gap-4'),
            appBar: AppBar::make('Connexion', backHref: '/'),
        );
    }
}
