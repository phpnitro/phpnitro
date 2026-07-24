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

use Engine\Html;
use Engine\Widget;

/**
 * Keychain/Keystore equivalent — an Android Keystore-backed
 * EncryptedSharedPreferences store (AES256-GCM), for tokens that shouldn't
 * sit in Engine\Preferences\'s plain SQLite table. Client-side only, same
 * as sensor readings/geolocation: PHP emits the trigger, the value lives
 * (encrypted) on the device, never round-tripped back into a PHP request
 * automatically — read it into an output element, or post it to your own
 * action if a server-side check needs it.
 */
final class SecureStorage
{
    public static function storeOnClick(string $key, string $value): string
    {
        return sprintf(
            'phpxDevice.secureStore(%s, %s)',
            json_encode($key, JSON_THROW_ON_ERROR),
            json_encode($value, JSON_THROW_ON_ERROR),
        );
    }

    public static function retrieveOnClick(string $key, string $outputId): string
    {
        return sprintf(
            "phpxDevice.showSecureValue(%s, '%s')",
            json_encode($key, JSON_THROW_ON_ERROR),
            $outputId,
        );
    }

    public static function removeOnClick(string $key): string
    {
        return sprintf('phpxDevice.secureRemove(%s)', json_encode($key, JSON_THROW_ON_ERROR));
    }

    public static function outputElement(string $id, string $classes = 'text-sm text-gray-500 dark:text-gray-400'): Widget
    {
        return Html::raw(sprintf(
            '<span id="%s" class="%s"></span>',
            htmlspecialchars($id, ENT_QUOTES),
            htmlspecialchars($classes, ENT_QUOTES),
        ));
    }
}
