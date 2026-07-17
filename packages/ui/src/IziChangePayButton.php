<?php

namespace Engine;

/**
 * iZiChangePay checkout widget — structural template, NOT verified against
 * a real account (no sandbox credentials available in this environment,
 * and lower confidence than Kkiapay/FedaPay on the exact script URL and JS
 * API shape). Same general aggregator-widget pattern as the other West
 * African gateways here (API key + widget script + a success callback
 * carrying a transaction reference) — check iZiChangePay's current
 * developer docs and adjust SCRIPT_URL and the init() call below before
 * using this for real.
 *
 * Same rule as every other gateway here: the success callback is a UI
 * signal only, never proof of payment. The handler receiving
 * `transaction_id` must verify it server-to-server with your API secret
 * before trusting it.
 */
final class IziChangePayButton extends Widget
{
    private const SCRIPT_URL = 'https://izichangepay.com/assets/widget.js'; // vérifie sur la doc iZiChangePay

    private const DEFAULT_CLASSES = 'w-full rounded-lg bg-purple-600 hover:bg-purple-700 text-white font-medium px-4 py-3';

    public function __construct(
        private readonly string $apiKey,
        private readonly float $amount,
        private readonly string $action,
        private readonly string $label = 'Payer',
        private readonly bool $sandbox = true,
        private readonly string $classes = self::DEFAULT_CLASSES,
    ) {
    }

    public static function make(
        string $apiKey,
        float $amount,
        string $action,
        string $label = 'Payer',
        bool $sandbox = true,
        string $classes = self::DEFAULT_CLASSES,
    ): self {
        return new self($apiKey, $amount, $action, $label, $sandbox, $classes);
    }

    public function render(): string
    {
        $id = 'izichangepay_' . substr(md5(uniqid('', true)), 0, 8);
        $scriptUrl = self::SCRIPT_URL;
        $apiKey = htmlspecialchars($this->apiKey, ENT_QUOTES);
        $action = htmlspecialchars($this->action, ENT_QUOTES);
        $classes = htmlspecialchars($this->classes, ENT_QUOTES);
        $label = htmlspecialchars($this->label, ENT_QUOTES);
        $amount = json_encode($this->amount);
        $sandbox = $this->sandbox ? 'true' : 'false';

        return <<<HTML
            <script src="{$scriptUrl}"></script>
            <button type="button" id="{$id}" class="{$classes}">{$label}</button>
            <script>
                document.getElementById('{$id}').addEventListener('click', function () {
                    // TODO: confirmer le nom exact de la fonction d'init dans la doc iZiChangePay actuelle.
                    IziChangePay.init({
                        api_key: '{$apiKey}',
                        amount: {$amount},
                        sandbox: {$sandbox},
                        onSuccess: function (response) {
                            const form = document.getElementById('{$id}').closest('form');
                            const body = form ? new URLSearchParams(new FormData(form)) : new URLSearchParams();
                            body.set('_action', '{$action}');
                            body.set('transaction_id', response.transaction_id);
                            const token = document.querySelector('meta[name="csrf-token"]');
                            if (token) {
                                body.set('_token', token.content);
                            }
                            fetch(window.location.pathname, { method: 'POST', body })
                                .then(() => window.location.reload());
                        },
                    });
                });
            </script>
            HTML;
    }
}
