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

/**
 * FedaPay checkout — same REST, server-to-server idiom as
 * Engine\Payments\Feexpay (file_get_contents()/stream_context_create(),
 * no curl: the PHP binary cross-compiled for Android has no curl
 * extension — see Feexpay's own docblock).
 *
 * The previous FedaPay integration (removed, see git history) mounted
 * `FedaPay.init(elementId, options)`, a client-side JS widget into a DOM
 * element — impossible against this app's native Canvas rendering (no
 * WebView, no DOM to mount into). FedaPay's REST API instead exposes a
 * hosted checkout page: pay() creates a transaction and hands back the
 * `payment_url` that response already carries — Engine\Device\
 * UrlLauncher opens it from a native screen. Same "client-side
 * completion is a UI signal only" rule as every other gateway here:
 * status() (a real server-to-server GET, using the SECRET key) is what
 * actually confirms a payment, never the redirect return alone.
 *
 * **Verified against a real sandbox transaction — two real
 * discrepancies vs. FedaPay's own published API reference
 * (docs.fedapay.com/api-reference/transactions/...), both confirmed by
 * inspecting the raw response:**
 * - Every response is wrapped as `{"v1/transaction": {...}}`, not the
 *   flat object the docs describe — unwrap() below.
 * - The docs describe a separate `POST /transactions/{id}/token` call to
 *   get a hosted checkout URL. Unnecessary: the transaction-creation
 *   response already carries `payment_url`/`payment_token` directly (a
 *   later GET on the same transaction returns both as `null` — the URL
 *   is only ever present on the response that creates it), so pay()
 *   only makes the one call.
 */
final class Fedapay
{
    private const SANDBOX_BASE_URL = 'https://sandbox-api.fedapay.com/v1';
    private const LIVE_BASE_URL = 'https://api.fedapay.com/v1';

    /**
     * Creates a transaction and returns the hosted checkout URL to
     * redirect the customer to. $reference is a caller-generated
     * correlation ID, stored as `merchant_reference` — FedaPay's own
     * `reference` field (returned below) is its own, separate
     * transaction reference.
     *
     * @return array{url: string, reference: string, transaction_id: int}|false
     */
    public static function pay(
        string $secretKey,
        float $amount,
        string $description,
        string $reference,
        ?string $email = null,
        ?string $firstName = null,
        ?string $lastName = null,
        bool $sandbox = true,
    ): array|false {
        $baseUrl = $sandbox ? self::SANDBOX_BASE_URL : self::LIVE_BASE_URL;

        $customer = array_filter([
            'email' => $email,
            'firstname' => $firstName,
            'lastname' => $lastName,
        ], static fn (?string $value): bool => $value !== null);

        $transaction = self::post($baseUrl . '/transactions', $secretKey, array_filter([
            'description' => $description,
            'amount' => self::wholeAmount($amount),
            'currency' => ['iso' => 'XOF'],
            'merchant_reference' => $reference,
            'customer' => $customer !== [] ? $customer : null,
        ], static fn (mixed $value): bool => $value !== null));

        $transactionId = $transaction['id'] ?? null;
        $url = $transaction['payment_url'] ?? null;
        if (!is_int($transactionId) || !is_string($url) || $url === '') {
            return false;
        }

        return [
            'url' => $url,
            'reference' => $transaction['reference'] ?? $reference,
            'transaction_id' => $transactionId,
        ];
    }

    /**
     * FedaPay's API rejects a non-integer amount — same XOF-has-no-subunit
     * reasoning as Feexpay::wholeAmount(), kept as its own float-in
     * signature for consistency with every other gateway in this package.
     */
    private static function wholeAmount(float $amount): int
    {
        return (int) round($amount);
    }

    /**
     * Real server-to-server status check — $transactionId is what pay()
     * returned as `transaction_id`, not the merchant-generated $reference.
     *
     * @return array{amount: mixed, status: string|null, reference: string}|false
     */
    public static function status(string $secretKey, int $transactionId, bool $sandbox = true): array|false
    {
        $baseUrl = $sandbox ? self::SANDBOX_BASE_URL : self::LIVE_BASE_URL;
        $data = self::get($baseUrl . "/transactions/{$transactionId}", $secretKey);

        if ($data === null) {
            return false;
        }

        return [
            'amount' => $data['amount'] ?? null,
            'status' => $data['status'] ?? null,
            'reference' => $data['merchant_reference'] ?? $data['reference'] ?? (string) $transactionId,
        ];
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>|null
     */
    private static function post(string $url, string $secretKey, array $fields): ?array
    {
        $response = @file_get_contents($url, false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n"
                    . "Accept: application/json\r\n"
                    . "Authorization: Bearer {$secretKey}\r\n",
                'content' => json_encode($fields, JSON_THROW_ON_ERROR),
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]));

        if ($response === false) {
            return null;
        }

        return self::unwrap(json_decode($response, true));
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function get(string $url, string $secretKey): ?array
    {
        $response = @file_get_contents($url, false, stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Accept: application/json\r\nAuthorization: Bearer {$secretKey}\r\n",
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]));

        if ($response === false) {
            return null;
        }

        return self::unwrap(json_decode($response, true));
    }

    /**
     * Every transaction response is wrapped as `{"v1/transaction": {...}}`
     * — see this class's own docblock for how that was confirmed (not
     * documented by FedaPay).
     */
    private static function unwrap(mixed $data): ?array
    {
        if (!is_array($data)) {
            return null;
        }

        $transaction = $data['v1/transaction'] ?? $data;

        return is_array($transaction) ? $transaction : null;
    }
}
