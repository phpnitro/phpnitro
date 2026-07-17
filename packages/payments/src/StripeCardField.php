<?php

namespace Engine\Payments;

use Engine\Widget;

/**
 * A real card-input widget — Stripe Elements, NOT a raw TextField-based
 * form. The card number/expiry/CVV are entered inside an iframe Stripe
 * itself controls (card.mount()); they never touch this app's DOM or
 * server. Only the resulting PaymentIntent id (already confirmed
 * client-side via stripe.confirmCardPayment) is posted to $action —
 * same "client success is a signal, not proof" rule as every other
 * gateway here, verified server-side via
 * StripeCheckout::retrievePaymentIntent() before creating an order.
 *
 * Building this with plain TextField inputs posting raw card data through
 * Form/$data to our own server would put that data in PCI-DSS SAQ D scope
 * (full audit, network segmentation...) — a real regression from every
 * other gateway in this package, all of which are hosted redirects or
 * vendor-JS widgets that never let raw card data reach our server. Elements
 * is Stripe's own documented way to avoid exactly that.
 *
 * Requires a PaymentIntent already created server-side (see
 * StripeCheckout::createPaymentIntent()) before this widget is rendered —
 * $clientSecret is what lets Stripe Elements confirm payment against that
 * specific PaymentIntent.
 */
final class StripeCardField extends Widget
{
    private const DEFAULT_CLASSES = 'w-full rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-4 py-3';

    public function __construct(
        private readonly string $publicKey,
        private readonly string $clientSecret,
        private readonly string $action,
        private readonly string $label = 'Payer par carte',
        private readonly string $classes = self::DEFAULT_CLASSES,
    ) {
    }

    public static function make(
        string $publicKey,
        string $clientSecret,
        string $action,
        string $label = 'Payer par carte',
        string $classes = self::DEFAULT_CLASSES,
    ): self {
        return new self($publicKey, $clientSecret, $action, $label, $classes);
    }

    public function render(): string
    {
        $id = 'stripe_' . substr(md5(uniqid('', true)), 0, 8);
        $publicKey = htmlspecialchars($this->publicKey, ENT_QUOTES);
        $clientSecret = htmlspecialchars($this->clientSecret, ENT_QUOTES);
        $action = htmlspecialchars($this->action, ENT_QUOTES);
        $classes = htmlspecialchars($this->classes, ENT_QUOTES);
        $label = htmlspecialchars($this->label, ENT_QUOTES);

        return <<<HTML
            <div id="{$id}_card" class="border border-gray-300 dark:border-gray-600 rounded-lg p-3 bg-white dark:bg-gray-800"></div>
            <div id="{$id}_error" class="hidden flex items-start gap-2 bg-red-50 dark:bg-red-950 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-200 rounded-lg p-3 text-sm mt-2"></div>
            <button type="button" id="{$id}_submit" class="{$classes} mt-3">{$label}</button>
            <script>
                (function () {
                    function showError(message) {
                        var errorEl = document.getElementById('{$id}_error');
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
                        card.mount('#{$id}_card');
                        card.on('change', function (event) {
                            showError(event.error ? event.error.message : '');
                        });

                        document.getElementById('{$id}_submit').addEventListener('click', function () {
                            stripe.confirmCardPayment('{$clientSecret}', { payment_method: { card: card } }).then(function (result) {
                                if (result.error) {
                                    showError(result.error.message);
                                    return;
                                }
                                var form = document.getElementById('{$id}_submit').closest('form');
                                window.phpxNav.submitForm(form, '{$action}', { payment_intent_id: result.paymentIntent.id });
                            });
                        });
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
            HTML;
    }
}
