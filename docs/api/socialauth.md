# Package `socialauth`

## `Engine\SocialAuth\AppleSignIn` (class)

sign_in_with_apple equivalent. Apple is the one provider here that doesn't take a static client_secret string — it requires a short-lived ES256-signed JWT generated from a private key downloaded once from Apple Developer's "Keys" section, built by clientSecret() below (same "sign our own JWT with openssl" idiom as Engine\Firebase\GoogleServiceAccount, RS256 there vs ES256 here). Apple also has no userinfo REST endpoint: name/email only ever arrive inside the id_token (a JWT) in the token response, and only on the user's FIRST authorization — store them yourself the first time, Apple won't send them again on subsequent logins.

### `static clientSecret(string $teamId, string $keyId, string $clientId, string $privateKeyPath, int $ttlSeconds = 15777000): string`

ES256-signed JWT Apple requires as the "client_secret" passed to exchangeCode() — $privateKeyPath is the .p8 file downloaded once from Apple Developer, $keyId its 10-character Key ID, $teamId your 10-character Apple Developer Team ID. Valid for $ttlSeconds (Apple allows up to 6 months; regenerate well before it expires since an expired one fails every login attempt until replaced).

## `Engine\SocialAuth\FacebookSignIn` (class)

UNVERIFIED — no real Facebook App available in this environment.

## `Engine\SocialAuth\GithubSignIn` (class)

UNVERIFIED — no real GitHub OAuth App available in this environment.

## `Engine\SocialAuth\GoogleSignIn` (class)

google_sign_in equivalent — standard OAuth2 Authorization Code flow (see OAuthProvider), not the Google Identity Services JS SDK: one consistent mechanism across every provider in this package rather than a different client-side SDK per provider.

## `Engine\SocialAuth\MicrosoftSignIn` (class)

Microsoft identity platform (Azure AD / personal Microsoft accounts), "common" tenant so both work. UNVERIFIED — no real Azure app registration available in this environment.

## `Engine\SocialAuth\OAuthProvider` (class)

Shared standard OAuth2 Authorization Code flow every provider in this package (Google, Microsoft, GitHub, Slack, Facebook, X) is built on — Apple also extends this but overrides token exchange for its ES256 client-secret JWT requirement (see AppleSignIn).

### `static onClick(string $clientId, string $redirectUri, ?string $scope = NULL): string`

### `static exchangeCode(string $code, string $clientId, string $clientSecret, string $redirectUri): ?array`

## `Engine\SocialAuth\SlackSignIn` (class)

"Sign in with Slack" (OpenID Connect on top of Slack's OAuth) — needs the openid/email/profile scopes, not Slack's older bot/workspace scopes. UNVERIFIED — no real Slack App available in this environment.

## `Engine\SocialAuth\XSignIn` (class)

X (formerly Twitter) OAuth2 — the only provider in this package that mandates PKCE. The code_verifier has to survive between onClick()'s redirect and exchangeCode() handling the callback, so it's round-tripped through the session (started by public/index.php for the whole app already) rather than requiring the caller to thread it through themselves.

### `static onClick(string $clientId, string $redirectUri, ?string $scope = NULL): string`

### `static exchangeCode(string $code, string $clientId, string $clientSecret, string $redirectUri): ?array`
