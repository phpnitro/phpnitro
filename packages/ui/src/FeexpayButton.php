<?php

namespace Engine;

/**
 * Feexpay checkout widget — structural template, NOT verified against
 * Feexpay's actual current SDK (no sandbox account available in this
 * environment, and lower confidence than Kkiapay/FedaPay on the exact
 * script URL and JS API shape). The pattern (public key/shop id + widget
 * script + a success callback carrying a transaction reference) is how
 * these West African aggregator widgets work in general — check Feexpay's
 * current developer docs and adjust SCRIPT_URL and the init() call below
 * before using this for real.
 *
 * Same rule as every other gateway here: the success callback is a UI
 * signal only, never proof of payment. The handler receiving
 * `transaction_id` must verify it server-to-server with your API key
 * before trusting it.
 */
final class FeexpayButton extends Widget
{
    private const SCRIPT_URL = 'https://checkout.feexpay.me/checkout.min.js'; // vérifie sur feexpay.me/docs

    private const DEFAULT_CLASSES = 'w-full rounded-lg bg-teal-600 hover:bg-teal-700 text-white font-medium px-4 py-3';

    public function __construct(
        private readonly string $shopId,
        private readonly float $amount,
        private readonly string $action,
        private readonly string $label = 'Payer',
        private readonly bool $sandbox = true,
        private readonly string $classes = self::DEFAULT_CLASSES,
    ) {
    }

    public static function make(
        string $shopId,
        float $amount,
        string $action,
        string $label = 'Payer',
        bool $sandbox = true,
        string $classes = self::DEFAULT_CLASSES,
    ): self {
        return new self($shopId, $amount, $action, $label, $sandbox, $classes);
    }

    public function render(): string
    {
        $id = 'feexpay_' . substr(md5(uniqid('', true)), 0, 8);
        $scriptUrl = self::SCRIPT_URL;
        $shopId = htmlspecialchars($this->shopId, ENT_QUOTES);
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
                    // TODO: confirmer le nom exact de la fonction d'init dans la doc Feexpay actuelle.
                    FeexPay.init({
                        shop_id: '{$shopId}',
                        amount: {$amount},
                        sandbox: {$sandbox},
                        callback: function (response) {
                            const form = document.getElementById('{$id}').closest('form');
                            window.phpxNav.submitForm(form, '{$action}', {
                                transaction_id: response.reference || response.transaction_id,
                            });
                        },
                    });
                });
            </script>
            HTML;
    }
}
