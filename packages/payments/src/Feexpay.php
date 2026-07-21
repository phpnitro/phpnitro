<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine\Payments;

use Engine\Html;
use Engine\Widget;

/**
 * Feexpay checkout — a JS trigger and script tag, not a pre-styled
 * widget: attach payOnClick() to any button via
 * Button::make($label, onClick: Feexpay::payOnClick(...)).
 *
 * Structural template, NOT verified against Feexpay's actual current
 * SDK (no sandbox account available in this environment, lower
 * confidence than Kkiapay/FedaPay on the exact script URL and JS API
 * shape) — check Feexpay's current developer docs and adjust
 * SCRIPT_URL/the init() call below before using this for real.
 *
 * The callback is a UI signal only, never proof of payment — the action
 * handler receiving `transaction_id` must verify it server-to-server
 * with your API key before trusting it.
 */
final class Feexpay
{
    private const SCRIPT_URL = 'https://checkout.feexpay.me/checkout.min.js'; // vérifie sur feexpay.me/docs

    public static function scriptTag(): Widget
    {
        return Html::raw('<script src="' . self::SCRIPT_URL . '"></script>');
    }

    public static function payOnClick(string $shopId, float $amount, string $action, bool $sandbox = true): string
    {
        $action = htmlspecialchars($action, ENT_QUOTES);

        return sprintf(
            "window.__phpxPaymentForm = this.closest('form'); "
            // TODO: confirmer le nom exact de la fonction d'init dans la doc Feexpay actuelle.
            . 'FeexPay.init({ shop_id: %s, amount: %s, sandbox: %s, callback: function (response) { '
            . "window.phpxNav.submitForm(window.__phpxPaymentForm, '{$action}', "
            . '{ transaction_id: response.reference || response.transaction_id }); } })',
            json_encode($shopId, JSON_THROW_ON_ERROR),
            json_encode($amount, JSON_THROW_ON_ERROR),
            $sandbox ? 'true' : 'false',
        );
    }
}
