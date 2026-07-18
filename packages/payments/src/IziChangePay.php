<?php

namespace Engine\Payments;

use Engine\Html;
use Engine\Widget;

/**
 * iZiChangePay checkout — a JS trigger and script tag, not a pre-styled
 * widget: attach payOnClick() to any button via
 * Button::make($label, onClick: IziChangePay::payOnClick(...)).
 *
 * Structural template, NOT verified against a real account (no sandbox
 * credentials available in this environment, lower confidence than
 * Kkiapay/FedaPay on the exact script URL and JS API shape) — check
 * iZiChangePay's current developer docs and adjust SCRIPT_URL/the
 * init() call below before using this for real.
 *
 * The callback is a UI signal only, never proof of payment — the action
 * handler receiving `transaction_id` must verify it server-to-server
 * with your API secret before trusting it.
 */
final class IziChangePay
{
    private const SCRIPT_URL = 'https://izichangepay.com/assets/widget.js'; // vérifie sur la doc iZiChangePay

    public static function scriptTag(): Widget
    {
        return Html::raw('<script src="' . self::SCRIPT_URL . '"></script>');
    }

    public static function payOnClick(string $apiKey, float $amount, string $action, bool $sandbox = true): string
    {
        $action = htmlspecialchars($action, ENT_QUOTES);

        return sprintf(
            "window.__phpxPaymentForm = this.closest('form'); "
            // TODO: confirmer le nom exact de la fonction d'init dans la doc iZiChangePay actuelle.
            . 'IziChangePay.init({ api_key: %s, amount: %s, sandbox: %s, onSuccess: function (response) { '
            . "window.phpxNav.submitForm(window.__phpxPaymentForm, '{$action}', { transaction_id: response.transaction_id }); } })",
            json_encode($apiKey, JSON_THROW_ON_ERROR),
            json_encode($amount, JSON_THROW_ON_ERROR),
            $sandbox ? 'true' : 'false',
        );
    }
}
