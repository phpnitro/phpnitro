<?php

namespace Engine;

/**
 * Renders (and consumes) the pending Flash message, if any, as a toast that
 * fades in then auto-dismisses via a pure CSS animation — no JS timer.
 * Place it once per screen, anywhere in the tree (it's fixed-position).
 */
final class FlashMessage extends Widget
{
    /**
     * @var array{message: string, type: string}|null
     */
    private readonly ?array $flash;

    public function __construct()
    {
        $this->flash = Flash::consume();
    }

    public static function make(): self
    {
        return new self();
    }

    public function render(): string
    {
        if ($this->flash === null) {
            return '';
        }

        $bg = match ($this->flash['type']) {
            'error' => 'bg-red-600',
            'info' => 'bg-blue-600',
            default => 'bg-green-600',
        };

        $message = htmlspecialchars($this->flash['message'], ENT_QUOTES);

        return '<div class="gpu-layer fixed top-4 left-1/2 -translate-x-1/2 z-50 ' . $bg . ' text-white '
            . 'px-4 py-2.5 rounded-lg shadow-lg text-sm font-medium phpx-flash">' . $message . '</div>';
    }
}
