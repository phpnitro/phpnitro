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
     * @return array<int, array{label: string, href: string, icon: string, onClick?: string}>
     */
    public static function items(): array
    {
        return [
            ['label' => 'Accueil', 'href' => '/', 'icon' => Icon::home()],
            ['label' => 'Réglages', 'href' => '/settings', 'icon' => Icon::settings()],
            // '/device' and '/api' have no WebView route anymore —
            // DevicePage.php/ApiPage.php were removed once their native
            // conversions reached full parity — these tabs open the native
            // screen that replaced them instead of navigating to a route
            // that no longer exists.
            ['label' => 'Device', 'href' => '#', 'icon' => Icon::camera(), 'onClick' => "phpxDevice.openNativeRenderPreviewAt('device')"],
            ['label' => 'API', 'href' => '#', 'icon' => Icon::link(), 'onClick' => "phpxDevice.openNativeRenderPreviewAt('api')"],
        ];
    }
}
