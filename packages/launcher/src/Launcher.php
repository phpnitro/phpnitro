<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine\Launcher;

/**
 * url_launcher equivalent — opens an external URI (website, phone dialer,
 * email client, SMS app) via the system's own handler, not this app's
 * WebView (see WebAppInterface.launchUrl() / MainActivity.kt's
 * shouldOverrideUrlLoading()). A JS trigger, not a widget — attach it to
 * any button via Button::make($label, onClick: Launcher::call('+229...')).
 */
final class Launcher
{
    public static function openUrl(string $url): string
    {
        return self::trigger($url);
    }

    public static function call(string $phoneNumber): string
    {
        return self::trigger('tel:' . self::stripForUri($phoneNumber));
    }

    public static function sms(string $phoneNumber, string $body = ''): string
    {
        $uri = 'sms:' . self::stripForUri($phoneNumber);
        if ($body !== '') {
            $uri .= '?body=' . rawurlencode($body);
        }

        return self::trigger($uri);
    }

    public static function email(string $address, string $subject = '', string $body = ''): string
    {
        $query = array_filter(['subject' => $subject, 'body' => $body]);
        $uri = 'mailto:' . rawurlencode($address);
        if ($query !== []) {
            $uri .= '?' . http_build_query($query);
        }

        return self::trigger($uri);
    }

    private static function stripForUri(string $phoneNumber): string
    {
        return preg_replace('/[^0-9+]/', '', $phoneNumber) ?? $phoneNumber;
    }

    private static function trigger(string $uri): string
    {
        return sprintf('phpxDevice.launchUrl(%s)', json_encode($uri, JSON_THROW_ON_ERROR));
    }
}
