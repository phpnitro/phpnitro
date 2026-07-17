<?php

namespace Engine;

/**
 * Real PayPal JS SDK button (paypal.Buttons().render()) — not a redirect,
 * the button renders in place. createOrder sets the amount client-side for
 * simplicity, matching the other gateway widgets here; for a high-value
 * production store, create the order server-side instead so the amount
 * can't be tampered with client-side.
 *
 * onApprove is a UI signal only, same rule as KkiapayButton — the handler
 * receiving `paypal_order_id` MUST call PayPal's server-to-server
 * /v2/checkout/orders/{id}/capture with your CLIENT SECRET before trusting
 * the payment. That call isn't exercised by anything in this codebase (no
 * sandbox app available here to test against).
 */
final class PaypalButton extends Widget
{
    public function __construct(
        private readonly string $clientId,
        private readonly float $amount,
        private readonly string $action,
        private readonly string $currency = 'EUR',
        private readonly string $classes = 'w-full',
    ) {
    }

    public static function make(
        string $clientId,
        float $amount,
        string $action,
        string $currency = 'EUR',
        string $classes = 'w-full',
    ): self {
        return new self($clientId, $amount, $action, $currency, $classes);
    }

    public function render(): string
    {
        $id = 'paypal_' . substr(md5(uniqid('', true)), 0, 8);
        $clientId = htmlspecialchars($this->clientId, ENT_QUOTES);
        $currency = htmlspecialchars($this->currency, ENT_QUOTES);
        $action = htmlspecialchars($this->action, ENT_QUOTES);
        $classes = htmlspecialchars($this->classes, ENT_QUOTES);
        $amount = json_encode(number_format($this->amount, 2, '.', ''));

        return <<<HTML
            <script src="https://www.paypal.com/sdk/js?client-id={$clientId}&currency={$currency}"></script>
            <div id="{$id}" class="{$classes}"></div>
            <script>
                paypal.Buttons({
                    createOrder: function (data, actions) {
                        return actions.order.create({
                            purchase_units: [{ amount: { value: {$amount}, currency_code: '{$currency}' } }],
                        });
                    },
                    onApprove: function (data) {
                        const form = document.getElementById('{$id}').closest('form');
                        return window.phpxNav.submitForm(form, '{$action}', { paypal_order_id: data.orderID });
                    },
                }).render('#{$id}');
            </script>
            HTML;
    }
}
