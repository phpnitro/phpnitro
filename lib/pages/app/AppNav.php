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
            ['label' => 'Device', 'href' => '/device', 'icon' => Icon::camera()],
            // '/api' has no WebView route anymore — ApiPage.php was
            // removed once NativeApiScreen.php reached full parity — this
            // tab opens the native screen that replaced it instead of
            // navigating to a route that no longer exists.
            ['label' => 'API', 'href' => '#', 'icon' => Icon::link(), 'onClick' => "phpxDevice.openNativeRenderPreviewAt('api')"],
        ];
    }
}
