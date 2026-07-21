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
 * Kkiapay checkout — a JS trigger and script tag, not a pre-styled
 * widget: attach payOnClick() to any button via
 * Button::make($label, onClick: Kkiapay::payOnClick(...)) instead of
 * being stuck with a fixed button rendering.
 *
 * Kkiapay's success callback (addSuccessListener) is registered globally
 * by its SDK, separately from the click that opens the widget — that's
 * why onSuccess() is its own piece, placed once anywhere on the page,
 * reading the form stashed by payOnClick() into a shared JS variable.
 *
 * The transactionId onSuccess() posts is a UI signal only, NOT proof of
 * payment — the action handler receiving it MUST call Kkiapay's
 * server-to-server /transactions/verify API with your PRIVATE key before
 * crediting anything.
 */
final class Kkiapay
{
    public static function scriptTag(): Widget
    {
        return Html::raw('<script src="https://cdn.kkiapay.me/k.js"></script>');
    }

    public static function payOnClick(string $publicKey, float $amount, bool $sandbox = true): string
    {
        return sprintf(
            "window.__phpxPaymentForm = this.closest('form'); openKkiapayWidget({ amount: %s, key: %s, sandbox: %s })",
            json_encode($amount, JSON_THROW_ON_ERROR),
            json_encode($publicKey, JSON_THROW_ON_ERROR),
            $sandbox ? 'true' : 'false',
        );
    }

    public static function onSuccess(string $action): Widget
    {
        $action = htmlspecialchars($action, ENT_QUOTES);

        return Html::raw(<<<HTML
            <script>
                addSuccessListener(function (response) {
                    window.phpxNav.submitForm(window.__phpxPaymentForm, '{$action}', { transaction_id: response.transactionId });
                });
            </script>
            HTML);
    }
}
