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
 * Opens whatever maps app the OS resolves for a "geo:" URI, centered on a
 * coordinate with an optional label — the external-app equivalent of
 * NativeWidgetsMapsScreen's own embedded osmdroid MapView, for when a
 * screen wants to hand the user off to their own maps app (turn-by-turn
 * directions, saved places) instead of showing the map itself. An
 * action-string builder, not a widget: attach MapLauncher::openAction()
 * to any Button.
 *
 * Fire-and-forget: there's no result field, the same way UrlLauncher has
 * none — there's no meaningful "it worked" signal beyond the app opening.
 */
final class MapLauncher
{
    public static function openAction(float $latitude, float $longitude, ?string $label = null): string
    {
        return "device:openmap:{$latitude}:{$longitude}:" . rawurlencode($label ?? '');
    }
}
