<?php

namespace Engine;

/**
 * Polls $endpoint (a route that returns a pre-rendered HTML fragment — plain
 * PHP output, not JSON) every $intervalMs and swaps it into the DOM. Keeps
 * PHP as the single source of truth for rendering: the client never
 * reimplements widget logic in JS, it just displays whatever HTML the
 * server sends on each poll.
 */
final class StreamBuilder extends Widget
{
    public function __construct(
        private readonly string $endpoint,
        private readonly Widget $initial,
        private readonly int $intervalMs = 2000,
        private readonly string $classes = '',
    ) {
    }

    public static function make(
        string $endpoint,
        Widget $initial,
        int $intervalMs = 2000,
        string $classes = '',
    ): self {
        return new self($endpoint, $initial, $intervalMs, $classes);
    }

    public function render(): string
    {
        $id = 'sb_' . substr(md5(uniqid('', true)), 0, 8);

        return sprintf(
            '<div id="%s" class="%s" data-stream-endpoint="%s" data-stream-interval="%d">%s</div>',
            $id,
            htmlspecialchars($this->classes, ENT_QUOTES),
            htmlspecialchars($this->endpoint, ENT_QUOTES),
            $this->intervalMs,
            $this->initial->render(),
        );
    }
}
