<?php

namespace Engine;

/**
 * WebView's Chromium engine supports <audio> with native transport
 * controls directly — no JS bridge needed, unlike Engine\Device\Sound
 * (which fires a one-shot sound effect via the native MediaPlayer bridge).
 */
final class AudioPlayer extends Widget
{
    public function __construct(
        private readonly string $src,
        private readonly bool $controls = true,
        private readonly bool $autoplay = false,
        private readonly bool $loop = false,
        private readonly string $classes = 'w-full',
    ) {
    }

    public static function make(
        string $src,
        bool $controls = true,
        bool $autoplay = false,
        bool $loop = false,
        string $classes = 'w-full',
    ): self {
        return new self($src, $controls, $autoplay, $loop, $classes);
    }

    public function render(): string
    {
        return sprintf(
            '<audio src="%s" class="%s"%s%s%s></audio>',
            htmlspecialchars($this->src, ENT_QUOTES),
            htmlspecialchars($this->classes, ENT_QUOTES),
            $this->controls ? ' controls' : '',
            $this->autoplay ? ' autoplay' : '',
            $this->loop ? ' loop' : '',
        );
    }
}
