<?php

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
