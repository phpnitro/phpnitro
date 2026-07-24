<?php

namespace Engine\Payments\Tests;

use Engine\Payments\Fedapay;
use Engine\Payments\IziChangePay;
use Engine\Payments\Kkiapay;
use Engine\Payments\PaypalButton;
use Engine\Payments\Stripe;
use Engine\Payments\TresorPay;
use PHPUnit\Framework\TestCase;

final class PaymentsTest extends TestCase
{
    public function testKkiapayScriptTagRendersSdkScript(): void
    {
        $this->assertStringContainsString('cdn.kkiapay.me/k.js', Kkiapay::scriptTag()->render());
    }

    public function testKkiapayPayOnClickStashesFormAndOpensWidget(): void
    {
        $js = Kkiapay::payOnClick('pk_test', 12.5);

        $this->assertStringContainsString("window.__phpxPaymentForm = this.closest('form')", $js);
        $this->assertStringContainsString('openKkiapayWidget({ amount: 12.5, key: "pk_test", sandbox: true })', $js);
    }

    public function testKkiapayOnSuccessRegistersGlobalListenerPostingToAction(): void
    {
        $html = Kkiapay::onSuccess('confirmKkiapay')->render();

        $this->assertStringContainsString('addSuccessListener(function (response)', $html);
        $this->assertStringContainsString("submitForm(window.__phpxPaymentForm, 'confirmKkiapay'", $html);
        $this->assertStringContainsString('transaction_id: response.transactionId', $html);
    }

    public function testFedapayScriptTagRendersSdkScript(): void
    {
        $this->assertStringContainsString('cdn.fedapay.com/checkout.js', Fedapay::scriptTag()->render());
    }

    public function testFedapayPayOnClickAssignsThrowawayIdAndCallsInit(): void
    {
        $js = Fedapay::payOnClick('pk_test', 10.0, 'confirmFedapay', 'Ma commande');

        $this->assertStringContainsString("if (!this.id) { this.id = 'fedapay_'", $js);
        $this->assertStringContainsString('FedaPay.init(this.id,', $js);
        $this->assertStringContainsString('public_key: "pk_test"', $js);
        $this->assertStringContainsString("submitForm(window.__phpxPaymentForm, 'confirmFedapay'", $js);
    }

    // Feexpay has no unit test here: since it's now a thin wrapper around
    // the real feexpay/feexpay-php SDK (Engine\Payments\Feexpay), every
    // method makes a real HTTP call — nothing left to assert without
    // either hitting the network in a test run or mocking the vendor SDK,
    // neither of which fits this suite. Verified instead by live sandbox
    // calls during development — see Feexpay.php's docblock.

    public function testIziChangePayPayOnClickCallsInitWithOnSuccess(): void
    {
        $js = IziChangePay::payOnClick('key1', 5.0, 'confirmIzichangepay');

        $this->assertStringContainsString('IziChangePay.init({', $js);
        $this->assertStringContainsString("submitForm(window.__phpxPaymentForm, 'confirmIzichangepay'", $js);
    }

    public function testTresorPayPayOnClickCallsInitWithOnSuccess(): void
    {
        $js = TresorPay::payOnClick('key1', 5.0, 'confirmTresorpay');

        $this->assertStringContainsString('TresorPay.init({', $js);
        $this->assertStringContainsString("submitForm(window.__phpxPaymentForm, 'confirmTresorpay'", $js);
    }

    public function testStripeCardElementMountsElementsAndStashesSharedState(): void
    {
        $html = Stripe::cardElement('pk_test', 'secret_123');

        $this->assertStringContainsString('id="phpx_stripe_card"', $html->render());
        $this->assertStringContainsString("window.__phpxStripe = { stripe: stripe, card: card, clientSecret: 'secret_123'", $html->render());
    }

    public function testStripeConfirmPaymentOnClickReadsSharedStateAndPosts(): void
    {
        $js = Stripe::confirmPaymentOnClick('confirmStripeCard');

        $this->assertStringContainsString('window.__phpxStripe.stripe.confirmCardPayment', $js);
        $this->assertStringContainsString("submitForm(window.__phpxPaymentForm, 'confirmStripeCard'", $js);
    }

    public function testPaypalButtonRendersSdkScriptWithClientIdAndCurrency(): void
    {
        $html = PaypalButton::make('client_123', 19.9, 'confirmPaypal', 'USD')->render();

        $this->assertStringContainsString('client-id=client_123&currency=USD', $html);
        $this->assertStringContainsString('value: "19.90", currency_code: \'USD\'', $html);
    }

    public function testPaypalButtonOnApprovePostsOrderIdToAction(): void
    {
        $html = PaypalButton::make('client_123', 10.0, 'confirmPaypal')->render();

        $this->assertStringContainsString("submitForm(form, 'confirmPaypal', { paypal_order_id: data.orderID })", $html);
    }

    // StripeCheckout has no unit test here, same reasoning as Feexpay above:
    // every public method makes a real HTTP call to api.stripe.com, nothing
    // left to assert without hitting the network or mocking file_get_contents
    // itself — neither fits this suite. Never exercised against a real
    // Stripe account in this environment (no sandbox credentials available).
}
