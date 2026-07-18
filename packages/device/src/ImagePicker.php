<?php

namespace Engine\Device;

use Engine\Html;
use Engine\Widget;

/**
 * Native image picker (system gallery/file app) with a live preview. The
 * picked image ends up as a data: URL in the hidden field the developer
 * places via hiddenField() — submits as part of a normal Form POST, same
 * as before, but the trigger/preview/hidden field are now composed
 * separately instead of bundled into one opinionated widget.
 */
final class ImagePicker
{
    public static function pickOnClick(string $previewId, string $hiddenFieldId): string
    {
        return sprintf("phpxDevice.pickImage('%s', '%s')", $previewId, $hiddenFieldId);
    }

    public static function hiddenField(string $name, string $id): Widget
    {
        return Html::raw(sprintf(
            '<input type="hidden" name="%s" id="%s">',
            htmlspecialchars($name, ENT_QUOTES),
            htmlspecialchars($id, ENT_QUOTES),
        ));
    }

    public static function previewElement(string $id, string $classes = 'w-full max-w-xs rounded-lg'): Widget
    {
        return Html::raw(sprintf(
            '<img id="%s" class="%s" alt="Image sélectionnée">',
            htmlspecialchars($id, ENT_QUOTES),
            htmlspecialchars($classes, ENT_QUOTES),
        ));
    }
}
