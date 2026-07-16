<?php

namespace Engine;

/**
 * Hamburger button that opens a Drawer — pass as AppBar's $leading.
 * Purely a <label for="phpx-drawer">, no JS: toggling the drawer's hidden
 * checkbox is what the browser already does natively for form controls.
 */
final class DrawerToggle extends Widget
{
    public static function make(): self
    {
        return new self();
    }

    public function render(): string
    {
        return '<label for="phpx-drawer" class="p-1 -ml-1 cursor-pointer text-gray-600 dark:text-gray-300" aria-label="Menu">'
            . Icon::hamburger()
            . '</label>';
    }
}
