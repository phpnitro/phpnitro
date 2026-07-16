<?php

namespace Engine;

/**
 * One-shot async: fetches $endpoint once on page load and swaps the
 * result in — unlike StreamBuilder, it never re-polls. $endpoint is a
 * route returning a pre-rendered HTML fragment, same convention as
 * StreamBuilder (PHP stays the single source of truth for rendering).
 */
final class FutureBuilder extends Widget
{
    public function __construct(
        private readonly string $endpoint,
        private readonly Widget $loading,
        private readonly string $classes = '',
    ) {
    }

    public static function make(string $endpoint, Widget $loading, string $classes = ''): self
    {
        return new self($endpoint, $loading, $classes);
    }

    public function render(): string
    {
        $id = 'fb_' . substr(md5(uniqid('', true)), 0, 8);

        return sprintf(
            '<div id="%s" class="%s" data-future-endpoint="%s">%s</div>',
            $id,
            htmlspecialchars($this->classes, ENT_QUOTES),
            htmlspecialchars($this->endpoint, ENT_QUOTES),
            $this->loading->render(),
        );
    }
}
