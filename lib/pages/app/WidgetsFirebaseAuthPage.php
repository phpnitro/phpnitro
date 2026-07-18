<?php

namespace Engine\App;

use Engine\Button;
use Engine\Column;
use Engine\Dialogs\ConfirmButton;
use Engine\Divider;
use Engine\ErrorBanner;
use Engine\Firebase\FirebaseAuth;
use Engine\Flash;
use Engine\FlashMessage;
use Engine\Form;
use Engine\Link;
use Engine\Screen;
use Engine\SingleScrollView;
use Engine\Text;
use Engine\TextField;
use Engine\Widget;

/**
 * Demo only — does NOT replace the existing session + UserRepository login
 * (LoginPage/RegisterPage), which stays fully functional. Needs
 * FIREBASE_WEB_API_KEY in .env; shows an explicit message instead of
 * attempting a call when it's absent, rather than a confusing network
 * failure.
 */
final class WidgetsFirebaseAuthPage extends Screen
{
    protected function initialState(): array
    {
        return ['error' => null];
    }

    /**
     * @param array<string, string> $data
     */
    protected function onSignIn(array $data): ?string
    {
        return $this->attempt(fn (string $key, string $email, string $password) => FirebaseAuth::signIn($key, $email, $password), $data);
    }

    /**
     * @param array<string, string> $data
     */
    protected function onSignUp(array $data): ?string
    {
        return $this->attempt(fn (string $key, string $email, string $password) => FirebaseAuth::signUp($key, $email, $password), $data);
    }

    /**
     * @param array<string, string> $data
     */
    protected function onFirebaseSignOut(array $data): ?string
    {
        unset($_SESSION['firebase_uid']);

        return null;
    }

    /**
     * @param array<string, string> $data
     */
    private function attempt(callable $call, array $data): ?string
    {
        $webApiKey = $_ENV['FIREBASE_WEB_API_KEY'] ?? '';
        if ($webApiKey === '') {
            $this->state['error'] = "FIREBASE_WEB_API_KEY n'est pas configuré dans .env — voir phpnitro.yml.";

            return null;
        }

        $result = $call($webApiKey, trim($data['email'] ?? ''), $data['password'] ?? '');

        if ($result['error'] !== null) {
            $this->state['error'] = "Échec Firebase Auth : {$result['error']}";

            return null;
        }

        $_SESSION['firebase_uid'] = $result['localId'];
        $this->state['error'] = null;
        Flash::set("Connecté via Firebase Auth (uid : {$result['localId']})");

        return null;
    }

    public function build(): Widget
    {
        $uid = $_SESSION['firebase_uid'] ?? null;

        $children = [
            Text::make('Firebase Authentication', 'text-2xl font-bold text-gray-900 dark:text-gray-100'),
            FlashMessage::make(),
            ErrorBanner::make($this->state['error']),
        ];

        if ($uid !== null) {
            $children[] = Text::make("Connecté — uid Firebase : {$uid}", 'text-gray-700 dark:text-gray-300');
            $children[] = ConfirmButton::make('Se déconnecter ?', action: 'firebaseSignOut', label: 'Se déconnecter');
        } else {
            $children[] = Form::make([
                TextField::make('email', label: 'Email', type: 'email'),
                TextField::make('password', label: 'Mot de passe', type: 'password'),
                Button::make('Se connecter', classes: 'bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded-lg w-full'),
            ], action: 'signIn');

            $children[] = Divider::make();

            $children[] = Form::make([
                TextField::make('email', label: 'Email'),
                TextField::make('password', label: 'Mot de passe', type: 'password'),
                Button::make('Créer un compte', classes: 'bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100 font-medium px-4 py-2 rounded-lg w-full'),
            ], action: 'signUp');
        }

        $children[] = Link::make('Retour à la vitrine', '/widgets');

        return SingleScrollView::make(Column::make($children, 'flex flex-col gap-4 p-4'));
    }
}
