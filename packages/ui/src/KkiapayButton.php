<?php

namespace Engine;

/**
 * Kkiapay checkout button (chosen as the one fully-wired payment gateway
 * pattern — Stripe/Fedapay/Feexpay/iZiChangePay/PayPal follow the same
 * shape: vendor checkout script + a button that opens it + a success
 * callback that POSTs the transaction id to a Screen action).
 *
 * On success, Kkiapay's client-side widget fires a JS callback with a
 * transactionId — that is NOT proof of payment, only a UI signal. This
 * button POSTs the transactionId to $action (an onXxx() handler on the
 * current Screen, same mechanism gestures.js uses for swipe/dblclick),
 * which MUST call Kkiapay's server-to-server /transactions/verify API
 * with your PRIVATE key before crediting anything.
 */
final class KkiapayButton extends Widget
{
    private const DEFAULT_CLASSES = 'w-full rounded-lg bg-green-600 hover:bg-green-700 text-white font-medium px-4 py-3';

    public function __construct(
        private readonly string $publicKey,
        private readonly float $amount,
        private readonly string $action,
        private readonly string $label = 'Payer',
        private readonly bool $sandbox = true,
        private readonly string $classes = self::DEFAULT_CLASSES,
    ) {
    }

    public static function make(
        string $publicKey,
        float $amount,
        string $action,
        string $label = 'Payer',
        bool $sandbox = true,
        string $classes = self::DEFAULT_CLASSES,
    ): self {
        return new self($publicKey, $amount, $action, $label, $sandbox, $classes);
    }

    public function render(): string
    {
        $id = 'kkiapay_' . substr(md5(uniqid('', true)), 0, 8);
        $publicKey = htmlspecialchars($this->publicKey, ENT_QUOTES);
        $action = htmlspecialchars($this->action, ENT_QUOTES);
        $classes = htmlspecialchars($this->classes, ENT_QUOTES);
        $label = htmlspecialchars($this->label, ENT_QUOTES);
        $amount = json_encode($this->amount);
        $sandbox = $this->sandbox ? 'true' : 'false';

        return <<<HTML
            <script src="https://cdn.kkiapay.me/k.js"></script>
            <button type="button" id="{$id}" class="{$classes}">{$label}</button>
            <script>
                document.getElementById('{$id}').addEventListener('click', function () {
                    openKkiapayWidget({ amount: {$amount}, key: '{$publicKey}', sandbox: {$sandbox} });
                });
                addSuccessListener(function (response) {
                    const body = new URLSearchParams();
                    body.set('_action', '{$action}');
                    body.set('transaction_id', response.transactionId);
                    const token = document.querySelector('meta[name="csrf-token"]');
                    if (token) {
                        body.set('_token', token.content);
                    }
                    fetch(window.location.pathname, { method: 'POST', body })
                        .then(() => window.location.reload());
                });
            </script>
            HTML;
    }
}
