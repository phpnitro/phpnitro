<?php

namespace Engine\App;

use Engine\Button;
use Engine\Color;
use Engine\Column;
use Engine\Connectivity\ConnectivityBadge;
use Engine\Divider;
use Engine\Form;
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

    // Phase 7 of docs/proposals/moteur-rendu-natif.md: a Preferences-backed
    // flag rather than a hardcoded constant — it's the mechanism the
    // roadmap actually calls for ("widget migration behind a flag"), and
    // Preferences already persists across restarts here for accent_color,
    // so this reuses the same primitive instead of inventing a new one.
    protected function onToggleNativePreview(array $data): ?string
    {
        $currentlyEnabled = Preferences::get('native_render_preview_enabled', '0') === '1';
        Preferences::set('native_render_preview_enabled', $currentlyEnabled ? '0' : '1');

        return null;
    }

    public function build(): Widget
    {
        $accent = Preferences::get('accent_color', 'blue');
        $nativePreviewEnabled = Preferences::get('native_render_preview_enabled', '0') === '1';

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

            Divider::make(),

            Text::make(
                'Moteur de rendu natif (expérimental) — layout à contraintes peint sur un Canvas natif, sans WebView.',
                'text-sm text-gray-500 dark:text-gray-400',
            ),
            Form::make([
                Button::make($nativePreviewEnabled ? 'Désactiver' : 'Activer'),
            ], action: 'toggleNativePreview'),
            $nativePreviewEnabled
                ? Button::make(
                    'Essayer le rendu natif',
                    onClick: 'phpxDevice.openNativeRenderPreview()',
                    background: Color::indigo(600),
                )
                : Text::make('Active le flag ci-dessus pour afficher le bouton.', 'text-xs text-gray-400 italic'),

            // '/' has no WebView route anymore — see ProductPage.php's
            // same fix.
            Button::make("Retour à l'accueil", onClick: "phpxDevice.openNativeRenderPreviewAt('home')", classes: 'text-blue-600 hover:underline text-left'),
        ], 'flex flex-col gap-4 p-4');
    }
}
