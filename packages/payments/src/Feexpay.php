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

use Feexpay\FeexpayPhp\FeexpayClass;

/**
 * Feexpay checkout — a thin wrapper around the real `feexpay/feexpay-php`
 * SDK (Composer package, added where this is used — see
 * examples/ecom/composer.json), not a client-side JS trigger like
 * Kkiapay/FedaPay: Feexpay's SDK does the USSD push / redirect URL
 * server-to-server, which is actually the natural fit for PhpNitro
 * (every interaction is already a full server round-trip).
 *
 * Verified against the real installed SDK source
 * (vendor/feexpay/feexpay-php/src/FeexpayClass.php, v2.0) and live
 * sandbox calls with real shop credentials — NOT against the vendor's own
 * published integration docs, which describe an older signature
 * (`paiementLocal($amount, $phone, $network, $name, $email)`, 5 args,
 * returning an array). The installed v2.0 actually requires
 * `$callbackInfo`/`$reference` too (7-8 args) and `paiementLocal`/
 * `paiementCard` return a bare reference string (or `false`), not an
 * array — only `requestToPayWeb` returns an array. This class matches
 * the installed code, since that's what actually runs.
 *
 * Two real findings from live testing, both on the vendor's side (not
 * patchable here without forking their package):
 * - `getIdAndMarchanName()` (called internally by every method above)
 *   confirmed the real shop credentials against Feexpay's API — returned
 *   the actual shop name.
 * - `payLocal()`'s underlying `curl_post()` sets no `CURLOPT_TIMEOUT` —
 *   a real call with a live phone number hung well past 20s with no
 *   response. A caller in production should assume this can block the
 *   request for a long time and design the UI around that (e.g. tell the
 *   customer to check their phone rather than waiting synchronously).
 * - `getPaiementStatus()` on an unknown/not-yet-settled reference returns
 *   an array with every field `null` (plus PHP warnings from the vendor
 *   code reading undefined properties on its decoded response) rather
 *   than throwing — status() below returns that as-is; callers must
 *   treat a `null` status as "not settled yet", not as an error.
 */
final class Feexpay
{
    private static function client(string $shopId, string $apiKey, string $callbackUrl, bool $sandbox): FeexpayClass
    {
        return new FeexpayClass($shopId, $apiKey, $callbackUrl, $sandbox ? 'SANDBOX' : 'LIVE');
    }

    /**
     * Triggers a real USSD push on the customer's phone (mobile money —
     * MTN, MOOV, CELTIIS BJ, MOOV TG, TOGOCOM TG, ORANGE SN, MTN CI, MTN
     * CG only, per Feexpay's own docs). The customer must confirm on
     * their phone; this call returns immediately with a reference to
     * poll via status() — it is NOT proof of payment by itself.
     *
     * $reference is a caller-generated correlation ID (e.g. the pending
     * order's ID) — Feexpay's own SDK docblock calls this `custom_id`.
     */
    public static function payLocal(
        string $shopId,
        string $apiKey,
        float $amount,
        string $phone,
        string $network,
        string $fullName,
        string $email,
        string $reference,
        bool $sandbox = true,
    ): string|false {
        return self::client($shopId, $apiKey, '', $sandbox)
            ->paiementLocal($amount, $phone, $network, $fullName, $email, '', $reference);
    }

    /**
     * Returns a hosted payment URL to redirect to (FREE SN, ORANGE CI,
     * MOOV CI, WAVE CI, MOOV BF, ORANGE BF) instead of a direct USSD push.
     *
     * @return array{payment_url: string, reference: string, order_id: string}|false
     */
    public static function payByWebUrl(
        string $shopId,
        string $apiKey,
        float $amount,
        string $phone,
        string $network,
        string $fullName,
        string $email,
        string $reference,
        string $cancelUrl,
        string $returnUrl,
        bool $sandbox = true,
    ): array|false {
        return self::client($shopId, $apiKey, '', $sandbox)
            ->requestToPayWeb($amount, $phone, $network, $fullName, $email, '', $reference, $cancelUrl, $returnUrl);
    }

    /**
     * Real server-to-server status check — unlike most other gateways in
     * this package, this one is fully confirmed against the actual
     * installed SDK, so its result can be trusted directly instead of the
     * "demo mode only" fallback the unverified gateways use.
     *
     * @return array{amount: mixed, clientNum: string, status: string, reference: string}|false
     */
    public static function status(string $shopId, string $apiKey, string $reference, bool $sandbox = true): array|false
    {
        return self::client($shopId, $apiKey, '', $sandbox)->getPaiementStatus($reference);
    }
}
