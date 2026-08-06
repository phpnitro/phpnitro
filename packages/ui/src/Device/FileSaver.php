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
 * Saves text content to the device's public Downloads folder via
 * MediaStore (the API 29+ scoped-storage way — no WRITE_EXTERNAL_STORAGE
 * permission needed, unlike the pre-scoped-storage File() approach). An
 * action-string builder, not a widget: attach FileSaver::saveAction() to
 * any Button.
 *
 * $content travels urlencoded inside the action string, so it's bounded
 * by whatever URL length the request pipeline tolerates — fine for a
 * note, a small JSON/CSV export, not an arbitrary large file. A project
 * saving something bigger (e.g. a downloaded blob) would call the
 * MediaStore APIs directly from Kotlin instead of going through this
 * generic demo path.
 *
 * Result lands in $_GET[$outputField] as 'Enregistré' or an error
 * message.
 */
final class FileSaver
{
    public static function saveAction(string $fileName, string $content, string $outputField = 'save_out'): string
    {
        return 'device:savefile:' . rawurlencode($fileName) . ':' . rawurlencode($content) . ":{$outputField}";
    }

    public static function result(string $outputField = 'save_out'): ?string
    {
        return $_GET[$outputField] ?? null;
    }
}
