<?php

namespace Engine;

final class CameraPreview extends Widget
{
    private const DEFAULT_CLASSES = 'bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100 '
        . 'font-medium px-4 py-2 rounded-lg';

    public function __construct(
        private readonly string $label = 'Activer la caméra',
        private readonly string $classes = self::DEFAULT_CLASSES,
    ) {
    }

    public static function make(string $label = 'Activer la caméra', string $classes = self::DEFAULT_CLASSES): self
    {
        return new self($label, $classes);
    }

    public function render(): string
    {
        $id = 'cam_' . substr(md5(uniqid('', true)), 0, 8);

        return sprintf(
            '<div class="flex flex-col gap-2">'
            . '<button type="button" onclick="phpxDevice.openCamera(\'%s\')" class="%s">%s</button>'
            . '<video id="%s" autoplay muted playsinline class="w-full max-w-xs rounded-lg bg-black"></video>'
            . '</div>',
            $id,
            htmlspecialchars($this->classes, ENT_QUOTES),
            htmlspecialchars($this->label, ENT_QUOTES),
            $id,
        );
    }
}
