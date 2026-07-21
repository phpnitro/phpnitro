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
 * Triggers the real native share sheet (Android's Intent.ACTION_SEND
 * chooser, iOS's UIActivityViewController — see WebAppInterface.share()) —
 * falls back to the Web Share API outside a native shell. A JS trigger, not
 * a widget — attach it to any button via
 * Button::make($label, onClick: Share::onClick(...)).
 */
final class Share
{
    public static function onClick(string $text, string $title = ''): string
    {
        return sprintf(
            'phpxDevice.share(%s, %s)',
            json_encode($text, JSON_THROW_ON_ERROR),
            json_encode($title, JSON_THROW_ON_ERROR),
        );
    }
}
