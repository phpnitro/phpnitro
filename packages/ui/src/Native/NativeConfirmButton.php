<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine\Native;

/**
 * The native-tree equivalent of Engine\Dialogs\ConfirmButton — a real
 * android.app.AlertDialog with Confirmer/Annuler buttons; $action only
 * reaches PHP (via the same fieldValues+refetch round-trip every other
 * submit:-style action uses) if the user actually taps Confirmer, same
 * "don't call the server until confirmed" guarantee the HTML pipeline's
 * phpxDialogs.confirm() callback gives.
 */
final class NativeConfirmButton implements RenderNode
{
    private readonly NativeButton $content;

    public function __construct(string $message, string $action, string $label = 'Confirmer', string $title = '')
    {
        $this->content = new NativeButton(
            $label,
            'dialog:confirm',
            background: Tokens::danger(),
            meta: ['message' => $message, 'title' => $title, 'confirmAction' => $action, 'label' => $label],
        );
    }

    public function layout(Constraints $constraints): Size
    {
        return $this->content->layout($constraints);
    }

    public function paint(NativeCanvas $canvas, float $x, float $y): void
    {
        $this->content->paint($canvas, $x, $y);
    }
}
