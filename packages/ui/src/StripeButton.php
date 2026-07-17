<?php

namespace Engine;

/**
 * Stripe Checkout (hosted page) redirect flow — the simplest correct Stripe
 * integration needs NO client-side SDK, so this is a plain submit button.
 * The real work happens server-side, in the Screen action handler this
 * posts to: call StripeCheckout::createSessionUrl() with your SECRET key
 * and return its result as the redirect (Screen::handle()'s return value
 * becomes a Location header, and that header can point anywhere, including
 * Stripe's own domain).
 *
 * Stripe redirects the customer back to whichever success_url you gave
 * createSessionUrl() — that route must still verify the session (via its
 * id, or a webhook) before marking an order paid, same "don't trust the
 * redirect alone" rule as every other gateway here.
 */
final class StripeButton extends Widget
{
    use RendersAction;

    private const DEFAULT_CLASSES = 'w-full rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-4 py-3';

    public function __construct(
        private readonly string $action,
        private readonly string $label = 'Payer par carte',
        private readonly string $classes = self::DEFAULT_CLASSES,
    ) {
    }

    public static function make(
        string $action,
        string $label = 'Payer par carte',
        string $classes = self::DEFAULT_CLASSES,
    ): self {
        return new self($action, $label, $classes);
    }

    public function render(): string
    {
        return $this->renderActionableButton($this->label, $this->action, $this->classes);
    }
}
