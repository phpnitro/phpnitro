<?php

namespace Engine;

/**
 * TresorPay checkout widget — structural template ONLY. Of all the
 * gateways here, this is the one with the lowest confidence: no verified
 * knowledge of TresorPay's actual script URL, JS API, or callback shape,
 * and no sandbox account available in this environment to check against.
 * This follows the same general aggregator-widget pattern as the other
 * gateways (API key + widget script + a success callback carrying a
 * transaction reference) as a starting skeleton — treat every identifier
 * below (SCRIPT_URL, TresorPay.init(), the callback name/shape) as a
 * placeholder to replace once you have TresorPay's real developer docs.
 *
 * Same rule as every other gateway here regardless: the success callback
 * is a UI signal only, never proof of payment. The handler receiving
 * `transaction_id` must verify it server-to-server with TresorPay before
 * trusting it.
 */
final class TresorPayButton extends Widget
{
    private const SCRIPT_URL = 'https://tresorpay.example/widget.js'; // À REMPLACER — voir la doc TresorPay

    private const DEFAULT_CLASSES = 'w-full rounded-lg bg-emerald-700 hover:bg-emerald-800 text-white font-medium px-4 py-3';

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
        $id = 'tresorpay_' . substr(md5(uniqid('', true)), 0, 8);
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
                    // TODO: remplace tout ce bloc par l'appel réel documenté par TresorPay.
                    TresorPay.init({
                        api_key: '{$apiKey}',
                        amount: {$amount},
                        sandbox: {$sandbox},
                        onSuccess: function (response) {
                            const form = document.getElementById('{$id}').closest('form');
                            window.phpxNav.submitForm(form, '{$action}', { transaction_id: response.transaction_id });
                        },
                    });
                });
            </script>
            HTML;
    }
}
