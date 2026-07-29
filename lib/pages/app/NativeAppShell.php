<?php

namespace Engine\App;

use Engine\Native\NativeBottomNavigation;

/**
 * The app's tab set is the same four destinations on every screen that
 * shows a bottom bar — centralized here instead of every Native*Screen
 * repeating the same four-item array, so adding/renaming a tab is a
 * one-file change.
 */
final class NativeAppShell
{
    public static function bottomNav(float $screenWidth, string $currentScreen): NativeBottomNavigation
    {
        return new NativeBottomNavigation($screenWidth, [
            ['icon' => 'home', 'label' => 'Accueil', 'screen' => 'home'],
            ['icon' => 'widgets', 'label' => 'Widgets', 'screen' => 'widgets'],
            ['icon' => 'smartphone', 'label' => 'Device', 'screen' => 'device'],
            ['icon' => 'api', 'label' => 'Backend', 'screen' => 'api'],
        ], $currentScreen);
    }
}
