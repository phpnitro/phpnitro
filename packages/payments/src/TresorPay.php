<?php

namespace Engine\Payments;

use Engine\Html;
use Engine\Widget;

/**
 * TresorPay checkout — a JS trigger and script tag, not a pre-styled
 * widget: attach payOnClick() to any button via
 * Button::make($label, onClick: TresorPay::payOnClick(...)).
 *
 * Structural template ONLY. Of all the gateways here, this is the one
 * with the lowest confidence: no verified knowledge of TresorPay's
 * actual script URL, JS API, or callback shape, and no sandbox account
 * available in this environment to check against — treat every
 * identifier below (SCRIPT_URL, TresorPay.init(), the callback
 * name/shape) as a placeholder to replace once you have TresorPay's
 * real developer docs.
 *
 * The callback is a UI signal only, never proof of payment — the action
 * handler receiving `transaction_id` must verify it server-to-server
 * with TresorPay before trusting it.
 */
final class TresorPay
{
    private const SCRIPT_URL = 'https://tresorpay.example/widget.js'; // À REMPLACER — voir la doc TresorPay

    public static function scriptTag(): Widget
    {
        return Html::raw('<script src="' . self::SCRIPT_URL . '"></script>');
    }

    public static function payOnClick(string $apiKey, float $amount, string $action, bool $sandbox = true): string
    {
        $action = htmlspecialchars($action, ENT_QUOTES);

        return sprintf(
            "window.__phpxPaymentForm = this.closest('form'); "
            // TODO: remplace tout ce bloc par l'appel réel documenté par TresorPay.
            . 'TresorPay.init({ api_key: %s, amount: %s, sandbox: %s, onSuccess: function (response) { '
            . "window.phpxNav.submitForm(window.__phpxPaymentForm, '{$action}', { transaction_id: response.transaction_id }); } })",
            json_encode($apiKey, JSON_THROW_ON_ERROR),
            json_encode($amount, JSON_THROW_ON_ERROR),
            $sandbox ? 'true' : 'false',
        );
    }
}
