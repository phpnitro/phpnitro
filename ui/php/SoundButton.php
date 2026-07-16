<?php

namespace Engine;

/**
 * Plays a sound file through the device speaker via native MediaPlayer
 * (see WebAppInterface.playSound) — keeps playing correctly across screen
 * lock / audio focus changes, unlike a WebView <audio> tag.
 */
final class SoundButton extends Widget
{
    private const DEFAULT_CLASSES = 'bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100 '
        . 'font-medium px-4 py-2 rounded-lg';

    public function __construct(
        private readonly string $url,
        private readonly string $label = 'Jouer le son',
        private readonly string $classes = self::DEFAULT_CLASSES,
    ) {
    }

    public static function make(
        string $url,
        string $label = 'Jouer le son',
        string $classes = self::DEFAULT_CLASSES,
    ): self {
        return new self($url, $label, $classes);
    }

    public function render(): string
    {
        return sprintf(
            '<button type="button" onclick="phpxDevice.playSound(%s)" class="%s">%s</button>',
            htmlspecialchars(json_encode($this->url), ENT_QUOTES),
            htmlspecialchars($this->classes, ENT_QUOTES),
            htmlspecialchars($this->label, ENT_QUOTES),
        );
    }
}
