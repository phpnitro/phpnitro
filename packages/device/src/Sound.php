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
 * Plays a sound file through the device speaker via native MediaPlayer
 * (see WebAppInterface.playSound) — keeps playing correctly across screen
 * lock / audio focus changes, unlike a WebView <audio> tag. A JS trigger,
 * not a widget — attach it to any button via
 * Button::make($label, onClick: Sound::onClick($url)).
 */
final class Sound
{
    public static function onClick(string $url): string
    {
        return sprintf('phpxDevice.playSound(%s)', json_encode($url, JSON_THROW_ON_ERROR));
    }
}
