# Package `firebase`

## `Engine\Firebase\FirebaseAuth` (class)

Signs END USERS in/up via Firebase's Identity Toolkit REST API — a different Google API and credential shape from GoogleServiceAccount: this authenticates with a web API key (client-safe by Firebase's own design, restricted by domain/package in the Firebase console), not a service account. Verifying a user's password happens server-side here (a plain REST call), consistent with this framework's "PHP renders and decides, minimal client JS" approach — no Firebase JS SDK involved.

### `static signUp(string $webApiKey, string $email, string $password): array`

### `static signIn(string $webApiKey, string $email, string $password): array`

## `Engine\Firebase\FirebaseMessaging` (class)

Sends a push via FCM's HTTP v1 API (POST .../messages:send), which requires a service-account bearer token — see GoogleServiceAccount.

### `static send(string $serviceAccountJsonPath, string $projectId, string $deviceToken, string $title, string $body): bool`

## `Engine\Firebase\Firestore` (class)

Minimal Firestore REST client (get/set one document) — no SDK, uses GoogleServiceAccount for auth like FirebaseMessaging. An ALTERNATIVE to Database::connection() (Doctrine DBAL/SQL), not plugged into it: Firestore is a document store, not a DBAL driver, so a repository that wants Firestore instead of SQL calls this class directly rather than Database::connection().

### `static get(string $serviceAccountJsonPath, string $projectId, string $collection, string $documentId): ?array`

exist or the request failed

### `static set(string $serviceAccountJsonPath, string $projectId, string $collection, string $documentId, array $fields): bool`

## `Engine\Firebase\GoogleServiceAccount` (class)

Google's "OAuth2 Service Account JWT Bearer" flow, pure PHP (openssl_sign, no SDK) — the standard, stable way a server authenticates itself (not a end user) against Google APIs. Shared by FirebaseMessaging (send) and Firestore, both of which authenticate the exact same way with a service account. Distinct from FirebaseAuth, which signs in END USERS via a web API key instead — a different Google API (Identity Toolkit) with a different credential shape entirely.

### `static signedJwt(string $serviceAccountJsonPath, string $scope, int $issuedAt): ?string`

Builds and signs the JWT asserting this service account, for the given scope — exposed separately from accessToken() so it can be unit-tested (structure + signature) without a network call.

### `static accessToken(string $serviceAccountJsonPath, string $scope): ?string`

Exchanges the signed JWT for a short-lived (1h) access token via POST {token_uri} — the actual network call, kept separate from signedJwt() for the same testability reason.
