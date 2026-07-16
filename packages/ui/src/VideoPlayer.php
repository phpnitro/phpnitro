<?php

namespace Engine;

final class VideoPlayer extends Widget
{
    public function __construct(
        private readonly string $src,
        private readonly bool $controls = true,
        private readonly bool $autoplay = false,
        private readonly bool $loop = false,
        private readonly string $poster = '',
        private readonly string $classes = 'w-full rounded-lg',
    ) {
    }

    public static function make(
        string $src,
        bool $controls = true,
        bool $autoplay = false,
        bool $loop = false,
        string $poster = '',
        string $classes = 'w-full rounded-lg',
    ): self {
        return new self($src, $controls, $autoplay, $loop, $poster, $classes);
    }

    public function render(): string
    {
        return sprintf(
            '<video src="%s" class="%s"%s%s%s%s></video>',
            htmlspecialchars($this->src, ENT_QUOTES),
            htmlspecialchars($this->classes, ENT_QUOTES),
            $this->poster !== '' ? ' poster="' . htmlspecialchars($this->poster, ENT_QUOTES) . '"' : '',
            $this->controls ? ' controls' : '',
            $this->autoplay ? ' autoplay muted' : '',
            $this->loop ? ' loop' : '',
        );
    }
}
