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
 * Reads/writes a Keystore-backed EncryptedSharedPreferences value (see
 * NativeDeviceBridge.kt's secureStore()/secureRetrieve(), the
 * "phpx_secure_storage" file) — an action-string builder, not a widget:
 * attach SecureStorage::storeAction()/SecureStorage::retrieveAction() to
 * any Button.
 *
 * Genuinely shared with the WebView rendering path (same Keystore file)
 * — a secret stored via one path is readable from the other. $value
 * travels urlencoded inside the action string, so it's bounded by
 * whatever URL length the request pipeline tolerates — fine for a
 * token/short credential, not an arbitrary blob.
 */
final class SecureStorage
{
    public static function storeAction(string $key, string $value): string
    {
        return 'device:securestore:' . rawurlencode($key) . ':' . rawurlencode($value);
    }

    public static function retrieveAction(string $key, string $outputField = 'secure_out'): string
    {
        return 'device:secureretrieve:' . rawurlencode($key) . ":{$outputField}";
    }

    public static function result(string $outputField = 'secure_out'): ?string
    {
        return $_GET[$outputField] ?? null;
    }
}
