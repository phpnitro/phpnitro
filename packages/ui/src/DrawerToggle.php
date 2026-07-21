<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
        // role="button" + tabindex: a bare <label> (even with a `for` and an
        // aria-label) isn't exposed as an interactive/focusable control by
        // Chromium's accessibility tree bridged into Android — confirmed
        // with a real accessibility dump (`uiautomator dump` under
        // TalkBack): the hamburger didn't appear in the tree AT ALL, not
        // even as an unlabeled node. Native click-through-label behavior
        // (toggling #phpx-drawer) still works unchanged, this only adds
        // the semantics a screen reader needs to find and activate it.
        return '<label for="phpx-drawer" class="p-1 -ml-1 cursor-pointer text-gray-600 dark:text-gray-300" '
            . 'role="button" tabindex="0" aria-label="Menu">'
            . Icon::hamburger()
            . '</label>';
    }
}
