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
 * NFC foreground-dispatch tag reading (see NativeRenderPocActivity.kt's
 * enableNfcForegroundDispatch()/onNewIntent() NFC handling) — an
 * action-string builder, not a widget: attach Nfc::startListeningAction()/
 * Nfc::stopListeningAction() to any Button.
 *
 * startListeningAction() has no result by itself — it just arms
 * foreground dispatch; a tag tapped while listening fires onNewIntent()
 * and lands in $_GET['nfc_out'] (fixed key, not configurable — same
 * "the Kotlin handler doesn't parse a custom output field" reasoning as
 * Camera::result()) on the NEXT request, whether that's from
 * stopListeningAction()'s own result() call or any other action taken
 * afterward. Always stop listening (stopListeningAction()) once done —
 * foreground dispatch left armed keeps intercepting NFC intents from
 * every other app on the device too.
 */
final class Nfc
{
    public static function startListeningAction(): string
    {
        return 'device:nfcstart';
    }

    public static function stopListeningAction(): string
    {
        return 'device:nfcstop';
    }

    public static function result(): ?string
    {
        return $_GET['nfc_out'] ?? null;
    }
}
