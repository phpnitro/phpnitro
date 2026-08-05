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
 * Lets the user pick an arbitrary file (any type, not just images — see
 * QrScanner/Camera for image-specific capture) via the system document
 * picker (ActivityResultContracts.OpenDocument). An action-string
 * builder, not a widget: attach FileSelector::pickAction() to any Button.
 *
 * Result lands in $_GET[$outputField] as the picked file's display name,
 * or 'Annulé' if the picker was dismissed without choosing anything —
 * not the file's actual bytes/content, the same "describe what happened,
 * not the raw payload" scope Camera's photo_out takes.
 */
final class FileSelector
{
    public static function pickAction(string $outputField = 'file_out'): string
    {
        return "device:pickfile:{$outputField}";
    }

    public static function result(string $outputField = 'file_out'): ?string
    {
        return $_GET[$outputField] ?? null;
    }
}
