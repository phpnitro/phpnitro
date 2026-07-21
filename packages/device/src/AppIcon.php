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
 * Switches the home-screen launcher icon at runtime (Android
 * activity-alias toggle — see AndroidManifest.xml's ".MainActivityDefault"/
 * ".MainActivityAlt" and WebAppInterface.kt's setAppIcon()). A JS trigger,
 * not a widget — attach it to any button via
 * Button::make($label, onClick: AppIcon::onClick('alt')).
 *
 * Only "default" and "alt" exist today (two mutually-exclusive
 * activity-alias entries) — adding a third icon means adding another alias
 * in the manifest, this isn't an open-ended registry.
 */
final class AppIcon
{
    public static function onClick(string $iconKey): string
    {
        return sprintf('phpxDevice.setAppIcon(%s)', json_encode($iconKey, JSON_THROW_ON_ERROR));
    }
}
