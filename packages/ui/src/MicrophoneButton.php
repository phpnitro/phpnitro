<?php

namespace Engine;

final class MicrophoneButton extends Widget
{
    private const DEFAULT_CLASSES = 'bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100 '
        . 'font-medium px-4 py-2 rounded-lg';

    public function __construct(
        private readonly string $label = 'Activer le micro',
        private readonly string $classes = self::DEFAULT_CLASSES,
    ) {
    }

    public static function make(string $label = 'Activer le micro', string $classes = self::DEFAULT_CLASSES): self
    {
        return new self($label, $classes);
    }

    public function render(): string
    {
        $id = 'mic_' . substr(md5(uniqid('', true)), 0, 8);

        return sprintf(
            '<div class="flex items-center gap-2">'
            . '<button type="button" onclick="phpxDevice.openMicrophone(\'%s\')" class="%s">%s</button>'
            . '<span id="%s" class="text-sm text-gray-500 dark:text-gray-400"></span>'
            . '</div>',
            $id,
            htmlspecialchars($this->classes, ENT_QUOTES),
            htmlspecialchars($this->label, ENT_QUOTES),
            $id,
        );
    }
}
