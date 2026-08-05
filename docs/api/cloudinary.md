# Package `cloudinary`

## `Engine\Cloudinary\Cloudinary` (class)

Image upload/transform/delete via Cloudinary's REST API directly (file_get_contents()/stream_context_create(), hand-built multipart body) — no `cloudinary/cloudinary_php` SDK dependency, same reasoning as Engine\Payments\Feexpay's own docblock: that SDK's HTTP layer (Guzzle, ultimately curl) has no guarantee of working on the PHP binary cross-compiled for Android (android/README.md's php-ndk build has no `curl` extension, confirmed the hard way for Feexpay). Only `openssl` (for the https:// stream wrapper) is needed here, and that gap is already closed for Feexpay/Stripe/OAuthProvider.

### `static upload(string $cloudName, string $apiKey, string $apiSecret, string $filePath, array $options = array (
)): array`

parameter that gets SIGNED (folder, public_id, tags, overwrite...) — see Cloudinary's own upload API reference for the full list. Do not put unsigned parameters (like upload_preset for unsigned uploads) here; this method only supports the signed flow.

### `static url(string $cloudName, string $publicId, array $transformations = array (
)): string`

Builds a delivery URL with Cloudinary's transformation mini-language — $transformations is `parameter => value` (e.g. ['width' => 400, 'height' => 400, 'crop' => 'fill', 'quality' => 'auto']) turned into "w_400,h_400,c_fill,q_auto/". No network call — this is pure string building, Cloudinary resolves the actual transformed image lazily on first request to the URL.

### `static destroy(string $cloudName, string $apiKey, string $apiSecret, string $publicId): bool`
