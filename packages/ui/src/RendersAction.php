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
 * Shared rendering for widgets that either do nothing on click (plain
 * <button>), submit a named action to the server (<form> + hidden input),
 * or trigger raw client-side JS ($onClick — how a service class like
 * Engine\Device\Vibrate or Engine\Payments\Kkiapay gets attached to a
 * button the developer controls, instead of being baked into an
 * opinionated pre-styled widget). $onClick takes priority over $action
 * when both are given — they're different mechanisms (client JS vs.
 * server POST), not meant to combine on the same element.
 */
trait RendersAction
{
    private function renderActionableButton(string $label, ?string $action, string $classes, ?string $onClick = null): string
    {
        $classes = htmlspecialchars($classes, ENT_QUOTES);
        $label = htmlspecialchars($label, ENT_QUOTES);

        if ($onClick !== null) {
            return sprintf(
                '<button type="button" onclick="%s" class="%s">%s</button>',
                htmlspecialchars($onClick, ENT_QUOTES),
                $classes,
                $label,
            );
        }

        if ($action === null) {
            // type=submit so a plain Button placed inside a Form submits it;
            // outside any form it is inert, same as type=button.
            return sprintf('<button type="submit" class="%s">%s</button>', $classes, $label);
        }

        $action = htmlspecialchars($action, ENT_QUOTES);

        return sprintf(
            '<form method="post" class="inline">'
            . '<input type="hidden" name="_action" value="%s">%s'
            . '<button type="submit" class="%s">%s</button>'
            . '</form>',
            $action,
            Csrf::field(),
            $classes,
            $label,
        );
    }
}
