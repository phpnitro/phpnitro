<?php

namespace Engine\Device;

use Engine\Html;
use Engine\Widget;

/**
 * Two triggers (open the live preview, capture a native photo) plus two
 * output elements (<video> for the preview, <img> for the captured
 * photo) — composed separately so the developer places each wherever
 * they want and attaches the triggers to their own buttons.
 */
final class Camera
{
    public static function openOnClick(string $videoId): string
    {
        return sprintf("phpxDevice.openCamera('%s')", $videoId);
    }

    public static function captureOnClick(string $imageId): string
    {
        return sprintf("phpxDevice.takeNativePhoto('%s')", $imageId);
    }

    public static function videoElement(string $id, string $classes = 'w-full max-w-xs rounded-lg bg-black'): Widget
    {
        return Html::raw(sprintf(
            '<video id="%s" autoplay muted playsinline class="%s"></video>',
            htmlspecialchars($id, ENT_QUOTES),
            htmlspecialchars($classes, ENT_QUOTES),
        ));
    }

    public static function imageElement(string $id, string $classes = 'w-full max-w-xs rounded-lg'): Widget
    {
        return Html::raw(sprintf(
            '<img id="%s" class="%s" alt="Photo native">',
            htmlspecialchars($id, ENT_QUOTES),
            htmlspecialchars($classes, ENT_QUOTES),
        ));
    }
}
