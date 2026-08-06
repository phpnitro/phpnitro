<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine\Device;

/**
 * Copies text to, or reads text from, the system clipboard via
 * android.content.ClipboardManager. An action-string builder, not a
 * widget: attach Clipboard::copyAction()/pasteAction() to any Button.
 *
 * Reading the clipboard (pasteAction) is restricted on Android 10+ for
 * apps that aren't the current input-method/default app in some cases —
 * the Kotlin side reports 'Presse-papiers vide ou inaccessible' rather
 * than crashing when a read comes back empty.
 */
final class Clipboard
{
    public static function copyAction(string $text): string
    {
        return 'device:clipboardcopy:' . rawurlencode($text);
    }

    public static function pasteAction(string $outputField = 'clipboard_out'): string
    {
        return "device:clipboardpaste:{$outputField}";
    }

    public static function result(string $outputField = 'clipboard_out'): ?string
    {
        return $_GET[$outputField] ?? null;
    }
}
