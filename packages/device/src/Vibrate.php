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
 * A JS trigger, not a widget — attach it to any button via
 * Button::make($label, onClick: Vibrate::onClick(200)) instead of being
 * stuck with a pre-styled widget's own rendering.
 */
final class Vibrate
{
    public static function onClick(int $milliseconds = 200): string
    {
        return "phpxDevice.vibrate({$milliseconds})";
    }
}
