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
 * Opens the system's default file manager app (see
 * NativeRenderPocActivity.kt's "filesapp" branch,
 * Intent.CATEGORY_APP_FILES) — an action-string builder, not a widget:
 * attach FilesApp::openAction() to any Button.
 *
 * Distinct from Engine\Device\FileSelector (picks ONE file into this
 * app) and Engine\Device\OpenFile (opens ONE specific file this app
 * wrote) — this jumps straight to the file manager itself, browsing
 * whatever it shows by default. Not every OEM ships an app that
 * registers for this category — a silent no-op on those devices, not a
 * crash. Fire-and-forget, no result field.
 */
final class FilesApp
{
    public static function openAction(): string
    {
        return 'device:filesapp';
    }
}
