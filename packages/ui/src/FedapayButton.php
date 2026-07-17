<?php

namespace Engine;

/**
 * FedaPay checkout widget (West African aggregator, same shape as
 * KkiapayButton: public key + widget script + a callback carrying a
 * transaction reference). Matches FedaPay's documented FedaPay.init()
 * pattern, but hasn't been tested against a real sandbox account in this
 * environment — verify the script URL and callback shape against FedaPay's
 * current docs before relying on this in production.
 *
 * onComplete is a UI signal only, same rule as KkiapayButton — the handler
 * receiving `transaction_id` MUST call FedaPay's server-to-server
 * transaction-status API with your SECRET key before trusting the payment.
 */
final class FedapayButton extends Widget
{
    private const DEFAULT_CLASSES = 'w-full rounded-lg bg-orange-600 hover:bg-orange-700 text-white font-medium px-4 py-3';

    public function __construct(
        private readonly string $publicKey,
        private readonly float $amount,
        private readonly string $action,
        private readonly string $description = '',
        private readonly string $label = 'Payer',
        private readonly bool $sandbox = true,
        private readonly string $classes = self::DEFAULT_CLASSES,
    ) {
    }

    public static function make(
        string $publicKey,
        float $amount,
        string $action,
        string $description = '',
        string $label = 'Payer',
        bool $sandbox = true,
        string $classes = self::DEFAULT_CLASSES,
    ): self {
        return new self($publicKey, $amount, $action, $description, $label, $sandbox, $classes);
    }

    public function render(): string
    {
        $id = 'fedapay_' . substr(md5(uniqid('', true)), 0, 8);
        $publicKey = htmlspecialchars($this->publicKey, ENT_QUOTES);
        $action = htmlspecialchars($this->action, ENT_QUOTES);
        $classes = htmlspecialchars($this->classes, ENT_QUOTES);
        $label = htmlspecialchars($this->label, ENT_QUOTES);
        $description = htmlspecialchars($this->description, ENT_QUOTES);
        $amount = json_encode($this->amount);
        $environment = $this->sandbox ? 'sandbox' : 'live';

        return <<<HTML
            <script src="https://cdn.fedapay.com/checkout.js?v=1.1.7"></script>
            <button type="button" id="{$id}" class="{$classes}">{$label}</button>
            <script>
                document.getElementById('{$id}').addEventListener('click', function () {
                    FedaPay.init('{$id}', {
                        public_key: '{$publicKey}',
                        environment: '{$environment}',
                        transaction: { amount: {$amount}, description: '{$description}' },
                        onComplete: function (response) {
                            if (response.reason !== FedaPay.CHECKOUT_COMPLETE) {
                                return;
                            }
                            const form = document.getElementById('{$id}').closest('form');
                            const body = form ? new URLSearchParams(new FormData(form)) : new URLSearchParams();
                            body.set('_action', '{$action}');
                            body.set('transaction_id', response.transaction.id);
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
