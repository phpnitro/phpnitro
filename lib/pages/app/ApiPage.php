<?php

namespace Engine\App;

use Backend\Kernel;
use Engine\BottomNavigation;
use Engine\Column;
use Engine\Link;
use Engine\Screen;
use Engine\Text;
use Engine\Widget;
use Symfony\Component\HttpFoundation\Request;

/**
 * Demonstrates the mobile UI calling the pure-PHP backend (backend/,
 * Symfony HttpFoundation). Both live in the SAME PHP process, so this is a
 * direct in-process call to Backend\Kernel — no HTTP round-trip, no second
 * server to launch. (A loopback HTTP call to itself would deadlock anyway:
 * PHP's built-in dev server handles one request at a time.)
 */
final class ApiPage extends Screen
{
    protected function initialState(): array
    {
        return [];
    }

    public function build(): Widget
    {
        return Column::make([
            Text::make('Backend PHP', 'text-2xl font-bold text-gray-900 dark:text-gray-100'),
            Text::make($this->fetchBackendMessage()),
            Link::make("Retour à l'accueil", '/'),
            BottomNavigation::make(AppNav::items(), variant: BottomNavigation::VARIANT_PILLS),
        ]);
    }

    private function fetchBackendMessage(): string
    {
        $request = Request::create('/api/hello');
        $data = json_decode((new Kernel())->handle($request)->getContent(), true);

        return $data['message'] ?? 'Réponse inattendue du backend.';
    }
}
