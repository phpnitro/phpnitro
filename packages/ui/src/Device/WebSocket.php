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
 * A REAL, persistent WebSocket connection to a server you run — not
 * polling in a costume. The connection itself lives in a foreground
 * Android service (see WebSocketService.kt), not in the request/response
 * PHP process on-device, precisely because a genuine push connection
 * needs a long-lived process with its own event loop, which the embedded
 * `php -S` server this app runs is not and cannot become without a much
 * bigger runtime change (Swoole/ReactPHP/Workerman). This class is an
 * action-string builder, not a widget: attach WebSocket::connectAction()
 * etc. to any Button of your choosing.
 *
 * Same "connect to your own hosted backend" model every mobile framework
 * actually uses (Flutter's web_socket_channel, React Native's WebSocket) —
 * this app is the CLIENT, never the server. If you're picturing
 * "phpnitro/shelf_web_socket", that package is Dart's SERVER-side
 * WebSocket support (shelf is a Dart server framework); the equivalent
 * here would be whatever backend you already run (Node, Laravel Reverb,
 * Swoole, Pusher/Ably...), not something this on-device PHP process
 * becomes.
 *
 * The connection survives this screen navigating away, the Activity
 * being destroyed/recreated (rotation, process death), and the app being
 * backgrounded — it only closes on an explicit disconnectAction() call.
 * Every message received updates $_GET[$outputField] and triggers a
 * fresh render on its own, with no tap required — genuine server push,
 * not "check back and see."
 */
final class WebSocket
{
    public static function connectAction(string $url, string $outputField = 'ws_out'): string
    {
        return 'device:wsconnect:' . rawurlencode($url) . ":{$outputField}";
    }

    public static function sendAction(string $message): string
    {
        return 'device:wssend:' . rawurlencode($message);
    }

    public static function disconnectAction(): string
    {
        return 'device:wsdisconnect';
    }

    public static function result(string $outputField = 'ws_out'): ?string
    {
        return $_GET[$outputField] ?? null;
    }
}
