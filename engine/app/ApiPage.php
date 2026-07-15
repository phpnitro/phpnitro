<?php

namespace Engine\App;

use Engine\BottomNavigation;
use Engine\Column;
use Engine\Link;
use Engine\Screen;
use Engine\Text;
use Engine\Widget;

/**
 * Demonstrates the mobile UI (engine/) calling the pure-PHP backend
 * (backend/, Symfony HttpFoundation) over HTTP — two separate PHP
 * processes talking to each other, both real PHP runtimes.
 */
final class ApiPage extends Screen
{
    private const BACKEND_URL = 'http://127.0.0.1:8091/api/hello';

    protected function initialState(): array
    {
        return [];
    }

    public function build(): Widget
    {
        $message = $this->fetchBackendMessage();

        return Column::make([
            Text::make('Backend PHP', 'text-2xl font-bold text-gray-900 dark:text-gray-100'),
            Text::make($message),
            Link::make("Retour à l'accueil", '/'),
            BottomNavigation::make(AppNav::items(), variant: BottomNavigation::VARIANT_PILLS),
        ]);
    }

    private function fetchBackendMessage(): string
    {
        $context = stream_context_create(['http' => ['timeout' => 2]]);
        $response = @file_get_contents(self::BACKEND_URL, false, $context);

        if ($response === false) {
            return 'Backend indisponible (lance `php -S 127.0.0.1:8091 -t public` dans backend/).';
        }

        $data = json_decode($response, true);

        return $data['message'] ?? 'Réponse inattendue du backend.';
    }
}
