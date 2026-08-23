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
 * Opens the system photo picker (ActivityResultContracts.GetContent, see
 * NativeRenderPocActivity.kt's pickImage launcher) — an action-string
 * builder, not a widget: attach ImagePicker::pickAction() to any Button.
 *
 * No READ_MEDIA_IMAGES/READ_EXTERNAL_STORAGE permission needed — the
 * system picker handles its own access grant, the same reasoning as
 * Camera's own docblock. Result always lands in $_GET['picked_image_out']
 * — a placeholder description today ("Image sélectionnée (N octets)" or
 * "Annulé"/"Erreur"), not the actual image bytes. Fixed key, not
 * configurable — same as Camera::result().
 */
final class ImagePicker
{
    public static function pickAction(): string
    {
        return 'device:pickimage';
    }

    public static function result(): ?string
    {
        return $_GET['picked_image_out'] ?? null;
    }
}
