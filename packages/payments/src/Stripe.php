<?php

namespace Engine\Payments;

use Engine\Html;
use Engine\Widget;

/**
 * Stripe Elements — a real card-input widget, NOT a raw TextField-based
 * form. The card number/expiry/CVV are entered inside an iframe Stripe
 * itself controls (card.mount()); they never touch this app's DOM or
 * server. Building this with plain TextField inputs posting raw card
 * data through Form/$data to our own server would put that data in
 * PCI-DSS SAQ D scope (full audit, network segmentation...) — Elements
 * is Stripe's own documented way to avoid exactly that, so cardElement()
 * stays a mount point rather than becoming a plain onClick trigger.
 *
 * confirmPaymentOnClick() IS decoupled from any specific button, though:
 * it reads the stripe/card instances cardElement() stashed into a shared
 * JS variable at mount time, confirms the PaymentIntent, and — only on
 * success — posts the id to $action, same "client success is a signal,
 * not proof" rule as every other gateway in this package, verified
 * server-side via StripeCheckout::retrievePaymentIntent() before
 * creating an order.
 *
 * Requires a PaymentIntent already created server-side (see
 * StripeCheckout::createPaymentIntent()) before cardElement() is
 * rendered — $clientSecret is what lets Stripe Elements confirm payment
 * against that specific PaymentIntent.
 */
final class Stripe
{
    public static function cardElement(
        string $publicKey,
        string $clientSecret,
        string $containerId = 'phpx_stripe_card',
        string $errorId = 'phpx_stripe_card_error',
    ): Widget {
        $publicKey = htmlspecialchars($publicKey, ENT_QUOTES);
        $clientSecret = htmlspecialchars($clientSecret, ENT_QUOTES);
        $containerId = htmlspecialchars($containerId, ENT_QUOTES);
        $errorId = htmlspecialchars($errorId, ENT_QUOTES);

        return Html::raw(<<<HTML
            <div id="{$containerId}" class="border border-gray-300 dark:border-gray-600 rounded-lg p-3 bg-white dark:bg-gray-800"></div>
            <div id="{$errorId}" class="hidden flex items-start gap-2 bg-red-50 dark:bg-red-950 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-200 rounded-lg p-3 text-sm mt-2"></div>
            <script>
                (function () {
                    function showError(message) {
                        var errorEl = document.getElementById('{$errorId}');
                        if (!message) {
                            errorEl.classList.add('hidden');
                            errorEl.textContent = '';
                            return;
                        }
                        errorEl.classList.remove('hidden');
                        errorEl.textContent = message;
                    }

                    function init() {
                        var stripe = Stripe('{$publicKey}');
                        var elements = stripe.elements();
                        var card = elements.create('card');
                        card.mount('#{$containerId}');
                        card.on('change', function (event) {
                            showError(event.error ? event.error.message : '');
                        });

                        window.__phpxStripe = { stripe: stripe, card: card, clientSecret: '{$clientSecret}', showError: showError };
                    }

                    // See OsmMap/MapboxMap for why Stripe.js is loaded dynamically
                    // with an onload callback instead of a plain <script src> tag
                    // followed by an inline script: a <script src> recreated by
                    // nav.js's executeScripts() (needed so this widget keeps
                    // working after a partial-navigation swap) executes
                    // asynchronously by default, so this init code could otherwise
                    // run before Stripe.js has finished loading.
                    if (window.Stripe) {
                        init();
                        return;
                    }
                    var script = document.createElement('script');
                    script.src = 'https://js.stripe.com/v3/';
                    script.onload = init;
                    document.head.appendChild(script);
                })();
            </script>
            HTML);
    }

    public static function confirmPaymentOnClick(string $action): string
    {
        $action = htmlspecialchars($action, ENT_QUOTES);

        return "window.__phpxPaymentForm = this.closest('form'); "
            . 'window.__phpxStripe.stripe.confirmCardPayment(window.__phpxStripe.clientSecret, '
            . '{ payment_method: { card: window.__phpxStripe.card } }).then(function (result) { '
            . 'if (result.error) { window.__phpxStripe.showError(result.error.message); return; } '
            . "window.phpxNav.submitForm(window.__phpxPaymentForm, '{$action}', { payment_intent_id: result.paymentIntent.id }); })";
    }
}
