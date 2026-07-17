<?php

namespace Engine;

/**
 * A distinct concern from Flash/FlashMessage: Flash is one-shot (set
 * before a redirect, shown once on the next page, then gone) and
 * fixed-position — wrong semantics for a validation error, which must stay
 * visible in the normal page flow across every failed submit until the
 * user actually fixes it. Screens keep this in their own $state (the same
 * way CheckoutPage already tracks $state['error']) and pass it straight
 * through: ErrorBanner::make($this->state['error']).
 */
final class ErrorBanner extends Widget
{
    private const DEFAULT_CLASSES = 'flex items-start gap-2 bg-red-50 dark:bg-red-950 border border-red-200 '
        . 'dark:border-red-800 text-red-800 dark:text-red-200 rounded-lg p-3 text-sm';

    public function __construct(
        private readonly ?string $message,
        private readonly string $classes = self::DEFAULT_CLASSES,
    ) {
    }

    public static function make(?string $message, string $classes = self::DEFAULT_CLASSES): self
    {
        return new self($message, $classes);
    }

    public function render(): string
    {
        if ($this->message === null || $this->message === '') {
            return '';
        }

        return sprintf(
            '<div class="%s">%s<span>%s</span></div>',
            htmlspecialchars($this->classes, ENT_QUOTES),
            Icon::warning('w-5 h-5 shrink-0 mt-0.5'),
            htmlspecialchars($this->message, ENT_QUOTES),
        );
    }
}
