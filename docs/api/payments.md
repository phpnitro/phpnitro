# Package `payments`

## `Engine\Payments\Fedapay` (class)

FedaPay checkout — a JS trigger and script tag, not a pre-styled widget: attach payOnClick() to any button via Button::make($label, onClick: Fedapay::payOnClick(...)).

### `static scriptTag(): Engine\Widget`

### `static payOnClick(string $publicKey, float $amount, string $action, string $description = '', bool $sandbox = true): string`

## `Engine\Payments\Feexpay` (class)

Feexpay checkout — talks to Feexpay's REST API directly via file_get_contents()/stream_context_create(), the same idiom every other server-to-server call in this codebase uses (StripeCheckout, Engine\SocialAuth\OAuthProvider), instead of depending on the `feexpay/feexpay-php` vendor SDK this class used previously.

### `static payLocal(string $shopId, string $apiKey, float $amount, string $phone, string $network, string $fullName, string $email, string $reference, bool $sandbox = true): string|false`

Triggers a real USSD push on the customer's phone (mobile money — MTN, MOOV, CELTIIS BJ, MOOV TG, TOGOCOM TG, ORANGE SN, MTN CI, MTN CG only, per Feexpay's own docs). The customer must confirm on their phone; this call returns immediately with a reference to poll via status() — it is NOT proof of payment by itself.

### `static payByWebUrl(string $shopId, string $apiKey, float $amount, string $phone, string $network, string $fullName, string $email, string $reference, string $cancelUrl, string $returnUrl, bool $sandbox = true): array|false`

Returns a hosted payment URL to redirect to (FREE SN, ORANGE CI, MOOV CI, WAVE CI, MOOV BF, ORANGE BF) instead of a direct USSD push.

### `static status(string $shopId, string $apiKey, string $reference, bool $sandbox = true): array|false`

Real server-to-server status check.

## `Engine\Payments\IziChangePay` (class)

iZiChangePay checkout — a JS trigger and script tag, not a pre-styled widget: attach payOnClick() to any button via Button::make($label, onClick: IziChangePay::payOnClick(...)).

### `static scriptTag(): Engine\Widget`

### `static payOnClick(string $apiKey, float $amount, string $action, bool $sandbox = true): string`

## `Engine\Payments\Kkiapay` (class)

Kkiapay checkout — a JS trigger and script tag, not a pre-styled widget: attach payOnClick() to any button via Button::make($label, onClick: Kkiapay::payOnClick(...)) instead of being stuck with a fixed button rendering.

### `static scriptTag(): Engine\Widget`

### `static payOnClick(string $publicKey, float $amount, bool $sandbox = true): string`

### `static onSuccess(string $action): Engine\Widget`

## `Engine\Payments\PaypalButton` (class)

Real PayPal JS SDK button (paypal.Buttons().render()) — deliberately still a widget, unlike Kkiapay/Fedapay/Feexpay/IziChangePay/TresorPay in this package. PayPal's SDK renders its OWN branded button INTO a container div it controls end-to-end (brand/compliance requirement on PayPal's side) — there is no `onclick` to extract and attach to an arbitrary developer-styled button, so the "service, not a widget" conversion doesn't apply here. This is a genuine SDK constraint, not a gap in this abstraction.

### `__construct(string $clientId, float $amount, string $action, string $currency = 'EUR', string $classes = 'w-full')`

### `static make(string $clientId, float $amount, string $action, string $currency = 'EUR', string $classes = 'w-full'): self`

### `render(): string`

## `Engine\Payments\Stripe` (class)

Stripe Elements — a real card-input widget, NOT a raw TextField-based form. The card number/expiry/CVV are entered inside an iframe Stripe itself controls (card.mount()); they never touch this app's DOM or server. Building this with plain TextField inputs posting raw card data through Form/$data to our own server would put that data in PCI-DSS SAQ D scope (full audit, network segmentation...) — Elements is Stripe's own documented way to avoid exactly that, so cardElement() stays a mount point rather than becoming a plain onClick trigger.

### `static cardElement(string $publicKey, string $clientSecret, string $containerId = 'phpx_stripe_card', string $errorId = 'phpx_stripe_card_error'): Engine\Widget`

### `static confirmPaymentOnClick(string $action): string`

## `Engine\Payments\StripeCheckout` (class)

Minimal wrapper around Stripe's REST API — hosted Checkout Sessions (createSessionUrl) and PaymentIntents (createPaymentIntent retrievePaymentIntent, used by Stripe::cardElement()'s own Elements-based card field) — no stripe-php SDK dependency, just the documented HTTP endpoints (Bearer auth with the secret key, form-encoded bodies). Not tested against a real Stripe account in this environment (no sandbox credentials available) — verify against Stripe's current API docs before relying on this in production.

### `static createSessionUrl(string $secretKey, int $amountCents, string $currency, string $productName, string $successUrl, string $cancelUrl): ?string`

### `static createPaymentIntent(string $secretKey, int $amountCents, string $currency): ?array`

Creates a PaymentIntent server-side (POST /v1/payment_intents) — the client_secret it returns is what Stripe::cardElement() needs to mount Stripe Elements and confirm the card client-side. Same REST-only approach as createSessionUrl(), same "not tested against a real Stripe account" caveat.

### `static retrievePaymentIntent(string $secretKey, string $paymentIntentId): ?array`

Re-fetches a PaymentIntent server-side (GET /v1/payment_intents/{id}) to confirm its real status before creating an order — the client's "it succeeded" signal is never trusted alone, same discipline as every other gateway here.

## `Engine\Payments\TresorPay` (class)

TresorPay checkout — a JS trigger and script tag, not a pre-styled widget: attach payOnClick() to any button via Button::make($label, onClick: TresorPay::payOnClick(...)).

### `static scriptTag(): Engine\Widget`

### `static payOnClick(string $apiKey, float $amount, string $action, bool $sandbox = true): string`
