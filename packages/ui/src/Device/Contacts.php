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
 * Counts the device's contacts via ContactsContract (see
 * NativeDeviceBridge.kt's contactsCount()) — an action-string builder,
 * not a widget: attach Contacts::countAction() to any Button.
 *
 * READ_CONTACTS is only ever checked, never requested — pair with
 * Permission::requestAction('contacts') first if it might not be
 * granted yet. Result lands in $_GET[$outputField] as "N contacts" or
 * "Permission requise".
 */
final class Contacts
{
    public static function countAction(string $outputField = 'contacts_out'): string
    {
        return "device:contacts:{$outputField}";
    }

    public static function result(string $outputField = 'contacts_out'): ?string
    {
        return $_GET[$outputField] ?? null;
    }
}
