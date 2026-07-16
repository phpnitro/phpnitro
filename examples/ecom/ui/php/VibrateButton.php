<?php

namespace Engine;

final class VibrateButton extends Widget
{
    private const DEFAULT_CLASSES = 'bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100 '
        . 'font-medium px-4 py-2 rounded-lg';

    public function __construct(
        private readonly string $label = 'Vibrer',
        private readonly int $milliseconds = 200,
        private readonly string $classes = self::DEFAULT_CLASSES,
    ) {
    }

    public static function make(
        string $label = 'Vibrer',
        int $milliseconds = 200,
        string $classes = self::DEFAULT_CLASSES,
    ): self {
        return new self($label, $milliseconds, $classes);
    }

    public function render(): string
    {
        return sprintf(
            '<button type="button" onclick="phpxDevice.vibrate(%d)" class="%s">%s</button>',
            $this->milliseconds,
            htmlspecialchars($this->classes, ENT_QUOTES),
            htmlspecialchars($this->label, ENT_QUOTES),
        );
    }
}
