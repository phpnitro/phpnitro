<?php

namespace Engine\Payments;

use Engine\Html;
use Engine\Widget;

/**
 * FedaPay checkout — a JS trigger and script tag, not a pre-styled
 * widget: attach payOnClick() to any button via
 * Button::make($label, onClick: Fedapay::payOnClick(...)).
 *
 * Matches FedaPay's documented FedaPay.init(elementId, options) pattern,
 * which needs a DOM element id to attach to — since the trigger button is
 * now the developer's own and may not have one, payOnClick() assigns a
 * throwaway id to `this` on the fly if it doesn't already have one.
 * Untested against a real sandbox account in this environment — verify
 * the script URL and callback shape against FedaPay's current docs
 * before relying on this in production.
 *
 * onComplete is a UI signal only, same rule as Kkiapay — the action
 * handler receiving `transaction_id` MUST call FedaPay's
 * server-to-server transaction-status API with your SECRET key before
 * trusting the payment.
 */
final class Fedapay
{
    public static function scriptTag(): Widget
    {
        return Html::raw('<script src="https://cdn.fedapay.com/checkout.js?v=1.1.7"></script>');
    }

    public static function payOnClick(
        string $publicKey,
        float $amount,
        string $action,
        string $description = '',
        bool $sandbox = true,
    ): string {
        $action = htmlspecialchars($action, ENT_QUOTES);

        return sprintf(
            "if (!this.id) { this.id = 'fedapay_' + Math.random().toString(36).slice(2); } "
            . "window.__phpxPaymentForm = this.closest('form'); "
            . "FedaPay.init(this.id, { public_key: %s, environment: %s, transaction: { amount: %s, description: %s }, "
            . "onComplete: function (response) { "
            . "if (response.reason !== FedaPay.CHECKOUT_COMPLETE) { return; } "
            . "window.phpxNav.submitForm(window.__phpxPaymentForm, '{$action}', { transaction_id: response.transaction.id }); "
            . '} })',
            json_encode($publicKey, JSON_THROW_ON_ERROR),
            json_encode($sandbox ? 'sandbox' : 'live', JSON_THROW_ON_ERROR),
            json_encode($amount, JSON_THROW_ON_ERROR),
            json_encode($description, JSON_THROW_ON_ERROR),
        );
    }
}
