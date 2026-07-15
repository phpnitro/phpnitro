<?php

namespace Engine\App;

use Engine\Icon;

/**
 * Shared bottom navigation items for every screen — avoids four copies of
 * the same route/icon list silently drifting out of sync.
 */
final class AppNav
{
    /**
     * @return array<int, array{label: string, href: string, icon: string}>
     */
    public static function items(): array
    {
        return [
            ['label' => 'Accueil', 'href' => '/', 'icon' => Icon::home()],
            ['label' => 'Réglages', 'href' => '/settings', 'icon' => Icon::settings()],
            ['label' => 'Device', 'href' => '/device', 'icon' => Icon::camera()],
            ['label' => 'API', 'href' => '/api', 'icon' => Icon::link()],
        ];
    }
}
