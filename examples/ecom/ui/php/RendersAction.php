<?php

namespace Engine;

/**
 * Shared rendering for widgets that either do nothing on click (plain
 * <button>) or submit a named action to the server (<form> + hidden input),
 * consistent with the "PHP is the real runtime" interaction model used
 * throughout the engine.
 */
trait RendersAction
{
    private function renderActionableButton(string $label, ?string $action, string $classes): string
    {
        $classes = htmlspecialchars($classes, ENT_QUOTES);
        $label = htmlspecialchars($label, ENT_QUOTES);

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
