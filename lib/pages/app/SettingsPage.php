<?php

namespace Engine\App;

use Engine\Button;
use Engine\Column;
use Engine\Connectivity\ConnectivityBadge;
use Engine\Divider;
use Engine\Form;
use Engine\Link;
use Engine\Preferences\Preferences;
use Engine\Row;
use Engine\Screen;
use Engine\SelectBox;
use Engine\Text;
use Engine\Widget;

/**
 * Demonstrates Engine\Preferences\ (persists across app restarts, unlike
 * $this->state which is per-session) and Engine\Connectivity\ (live
 * online/offline badge, painted client-side — see
 * assets/js/connectivity.js).
 */
final class SettingsPage extends Screen
{
    protected function initialState(): array
    {
        return [];
    }

    protected function onSetAccent(array $data): ?string
    {
        Preferences::set('accent_color', $data['accent'] ?? 'blue');

        return null;
    }

    public function build(): Widget
    {
        $accent = Preferences::get('accent_color', 'blue');

        return Column::make([
            Text::make('Réglages', 'text-2xl font-bold text-gray-900 dark:text-gray-100'),

            Row::make([
                Text::make('Réseau :', 'text-sm text-gray-500 dark:text-gray-400'),
                ConnectivityBadge::make(),
            ], 'flex items-center gap-2'),

            Divider::make(),

            Text::make(
                "Couleur d'accent (Engine\\Preferences\\ — persiste après un redémarrage de l'app, contrairement à \$this->state)",
                'text-sm text-gray-500 dark:text-gray-400',
            ),
            Text::make("Valeur actuelle : {$accent}"),
            Form::make([
                SelectBox::make('accent', [
                    'blue' => 'Bleu',
                    'purple' => 'Violet',
                    'emerald' => 'Émeraude',
                ], selected: $accent),
                Button::make('Enregistrer'),
            ], action: 'setAccent'),

            Link::make("Retour à l'accueil", '/'),
        ], 'flex flex-col gap-4 p-4');
    }
}
