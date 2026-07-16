<?php

namespace Engine;

final class CameraPreview extends Widget
{
    private const DEFAULT_CLASSES = 'bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100 '
        . 'font-medium px-4 py-2 rounded-lg';

    public function __construct(
        private readonly string $label = 'Activer la caméra',
        private readonly string $nativeLabel = 'Photo native',
        private readonly string $classes = self::DEFAULT_CLASSES,
    ) {
    }

    public static function make(
        string $label = 'Activer la caméra',
        string $nativeLabel = 'Photo native',
        string $classes = self::DEFAULT_CLASSES,
    ): self {
        return new self($label, $nativeLabel, $classes);
    }

    public function render(): string
    {
        $id = 'cam_' . substr(md5(uniqid('', true)), 0, 8);
        $imgId = 'camimg_' . substr(md5(uniqid('', true)), 0, 8);
        $classes = htmlspecialchars($this->classes, ENT_QUOTES);

        return sprintf(
            '<div class="flex flex-col gap-2">'
            . '<div class="flex gap-2">'
            . '<button type="button" onclick="phpxDevice.openCamera(\'%s\')" class="%s">%s</button>'
            . '<button type="button" onclick="phpxDevice.takeNativePhoto(\'%s\')" class="%s">%s</button>'
            . '</div>'
            . '<video id="%s" autoplay muted playsinline class="w-full max-w-xs rounded-lg bg-black"></video>'
            . '<img id="%s" class="w-full max-w-xs rounded-lg" alt="Photo native">'
            . '</div>',
            $id,
            $classes,
            htmlspecialchars($this->label, ENT_QUOTES),
            $imgId,
            $classes,
            htmlspecialchars($this->nativeLabel, ENT_QUOTES),
            $id,
            $imgId,
        );
    }
}
