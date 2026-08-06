# Package `payments`

## `Engine\Payments\Feexpay` (class)

Feexpay checkout — talks to Feexpay's REST API directly via file_get_contents()/stream_context_create(), the same idiom every other server-to-server call in this codebase uses (StripeCheckout, Engine\SocialAuth\OAuthProvider), instead of depending on the `feexpay/feexpay-php` vendor SDK this class used previously.

### `static payLocal(string $shopId, string $apiKey, float $amount, string $phone, string $network, string $fullName, string $email, string $reference, bool $sandbox = true): string|false`

Triggers a real USSD push on the customer's phone (mobile money — MTN, MOOV, CELTIIS BJ, MOOV TG, TOGOCOM TG, ORANGE SN, MTN CI, MTN CG only, per Feexpay's own docs). The customer must confirm on their phone; this call returns immediately with a reference to poll via status() — it is NOT proof of payment by itself.

### `static payByWebUrl(string $shopId, string $apiKey, float $amount, string $phone, string $network, string $fullName, string $email, string $reference, string $cancelUrl, string $returnUrl, bool $sandbox = true): array|false`

Returns a hosted payment URL to redirect to (FREE SN, ORANGE CI, MOOV CI, WAVE CI, MOOV BF, ORANGE BF) instead of a direct USSD push.

### `static status(string $shopId, string $apiKey, string $reference, bool $sandbox = true): array|false`

Real server-to-server status check.
