<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine;

/**
 * animated_text_kit equivalent — cycles a typewriter effect through a list
 * of strings (assets/js/animated-text.js).
 *
 * @param string[] $texts
 */
final class AnimatedText extends Widget
{
    /**
     * @param string[] $texts
     */
    public function __construct(
        private readonly array $texts,
        private readonly int $typeSpeedMs = 60,
        private readonly int $pauseMs = 1200,
        private readonly int $deleteSpeedMs = 30,
        private readonly string $classes = 'text-lg font-medium',
    ) {
    }

    /**
     * @param string[] $texts
     */
    public static function make(
        array $texts,
        int $typeSpeedMs = 60,
        int $pauseMs = 1200,
        int $deleteSpeedMs = 30,
        string $classes = 'text-lg font-medium',
    ): self {
        return new self($texts, $typeSpeedMs, $pauseMs, $deleteSpeedMs, $classes);
    }

    public function render(): string
    {
        return sprintf(
            '<span data-animated-text data-texts="%s" data-type-speed-ms="%d" data-pause-ms="%d" '
            . 'data-delete-speed-ms="%d" class="%s"></span>',
            htmlspecialchars(json_encode($this->texts, JSON_THROW_ON_ERROR), ENT_QUOTES),
            $this->typeSpeedMs,
            $this->pauseMs,
            $this->deleteSpeedMs,
            htmlspecialchars($this->classes, ENT_QUOTES),
        );
    }
}
