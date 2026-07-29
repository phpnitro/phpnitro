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
 * The native-tree equivalent of Engine\Dialogs\AlertButton — a real
 * android.app.AlertDialog instead of phpxDialogs.alert()'s JS confirm()
 * shim, which is what a native app should show in the first place (no
 * WebView chrome awkwardly hosting what looks like a browser dialog).
 * Message/title travel in the hit region's meta; the dialog needs no
 * server round-trip at all, so there's no PHP-side handling to match
 * ConfirmButton's action.
 */
final class NativeAlertButton implements RenderNode
{
    private readonly NativeButton $content;

    public function __construct(string $message, string $label = 'Afficher un message', string $title = '')
    {
        $this->content = new NativeButton(
            $label,
            'dialog:alert',
            width: null,
            background: Tokens::surfaceMuted(),
            foreground: Tokens::ink(),
            meta: ['message' => $message, 'title' => $title],
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
