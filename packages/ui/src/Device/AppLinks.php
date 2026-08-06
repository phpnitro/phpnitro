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
 * Reads the deep link URI (if any) that launched or last resumed the
 * app — NativeRenderPocActivity already auto-navigates a "phpnitro://"
 * link to the matching screen (see its own deepLinkScreenToken()); this
 * is for a screen that wants the raw link itself instead (e.g. to read a
 * query parameter, or decide something other than "navigate" from it).
 *
 * An action-string builder, not a widget: attach AppLinks::lastLinkAction()
 * to any Button of your choosing.
 *
 * Result lands in $_GET[$outputField] as the full URI string, or
 * 'Aucun lien' if the app wasn't opened via a deep link.
 */
final class AppLinks
{
    public static function lastLinkAction(string $outputField = 'app_link_out'): string
    {
        return "device:applink:{$outputField}";
    }

    public static function result(string $outputField = 'app_link_out'): ?string
    {
        return $_GET[$outputField] ?? null;
    }
}
