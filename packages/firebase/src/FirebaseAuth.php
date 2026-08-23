<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine\Firebase;

/**
 * Signs END USERS in/up via Firebase's Identity Toolkit REST API — a
 * different Google API and credential shape from GoogleServiceAccount:
 * this authenticates with a web API key (client-safe by Firebase's own
 * design, restricted by domain/package in the Firebase console), not a
 * service account. Verifying a user's password happens server-side here
 * (a plain REST call), consistent with this framework's "PHP renders and
 * decides, minimal client JS" approach — no Firebase JS SDK involved.
 *
 * Not tested against a real Firebase project in this environment (no web
 * API key available here) — same confidence tier as Mapbox/Google Maps.
 */
final class FirebaseAuth
{
    /**
     * @return array{idToken: string, localId: string, refreshToken: string, error: null}|array{idToken: null, localId: null, refreshToken: null, error: string}
     */
    public static function signUp(string $webApiKey, string $email, string $password): array
    {
        return self::call('accounts:signUp', $webApiKey, $email, $password);
    }

    /**
     * @return array{idToken: string, localId: string, refreshToken: string, error: null}|array{idToken: null, localId: null, refreshToken: null, error: string}
     */
    public static function signIn(string $webApiKey, string $email, string $password): array
    {
        return self::call('accounts:signInWithPassword', $webApiKey, $email, $password);
    }

    /**
     * Exchanges a Google ID token (obtained on-device via Android's
     * Credential Manager — see NativeDeviceBridge.kt's
     * signInWithGoogle()) for a Firebase session, through Identity
     * Toolkit's federated-identity endpoint. The ID token itself was
     * already issued by Google and proves the user's Google identity;
     * this call's only job is telling Firebase "trust this token, create
     * or look up the matching Firebase user." No client-side Firebase
     * SDK involved, same as signIn()/signUp() above.
     *
     * requestUri is a required field of this endpoint's request shape,
     * not something actually validated for a native-app flow (it matters
     * for the web OAuth redirect flow this endpoint was originally
     * designed for) — a fixed placeholder is Firebase's own documented
     * approach for non-web callers.
     *
     * Not tested against a real Firebase project in this environment (no
     * web API key or Google Cloud OAuth Web Client ID available here) —
     * same confidence tier as signIn()/signUp() above.
     *
     * @return array{idToken: string, localId: string, refreshToken: string, error: null}|array{idToken: null, localId: null, refreshToken: null, error: string}
     */
    public static function signInWithGoogleIdToken(string $webApiKey, string $googleIdToken): array
    {
        return self::post('accounts:signInWithIdp', $webApiKey, [
            'postBody' => "id_token={$googleIdToken}&providerId=google.com",
            'requestUri' => 'http://localhost',
            'returnSecureToken' => true,
        ]);
    }

    /**
     * Exchanges a Facebook OAuth access token (see
     * Engine\SocialAuth\FacebookSignIn::exchangeCode() — its 'access_token'
     * key, added specifically to feed this call) for a Firebase session,
     * same signInWithIdp mechanism as signInWithGoogleIdToken() above but
     * with Facebook's own postBody shape ("access_token=...", not
     * "id_token=..." — Facebook's OAuth2 flow issues an access token, not
     * an OIDC ID token like Google's does).
     *
     * @return array{idToken: string, localId: string, refreshToken: string, error: null}|array{idToken: null, localId: null, refreshToken: null, error: string}
     */
    public static function signInWithFacebookAccessToken(string $webApiKey, string $facebookAccessToken): array
    {
        return self::signInWithIdp($webApiKey, 'facebook.com', $facebookAccessToken);
    }

    /**
     * Same as signInWithFacebookAccessToken(), for
     * Engine\SocialAuth\GithubSignIn::exchangeCode()'s access token —
     * GitHub is also a plain OAuth2 provider (no ID token), same
     * "access_token=..." postBody shape.
     *
     * @return array{idToken: string, localId: string, refreshToken: string, error: null}|array{idToken: null, localId: null, refreshToken: null, error: string}
     */
    public static function signInWithGithubAccessToken(string $webApiKey, string $githubAccessToken): array
    {
        return self::signInWithIdp($webApiKey, 'github.com', $githubAccessToken);
    }

    /**
     * @return array{idToken: string, localId: string, refreshToken: string, error: null}|array{idToken: null, localId: null, refreshToken: null, error: string}
     */
    private static function signInWithIdp(string $webApiKey, string $providerId, string $accessToken): array
    {
        return self::post('accounts:signInWithIdp', $webApiKey, [
            'postBody' => "access_token={$accessToken}&providerId={$providerId}",
            'requestUri' => 'http://localhost',
            'returnSecureToken' => true,
        ]);
    }

    /**
     * Triggers Firebase's own password-reset email (Identity Toolkit
     * sends it directly — no SMTP/mail setup needed on this app's own
     * backend, same "PHP decides, Google's infrastructure does the
     * actual delivery" split as signIn()/signUp()). Always reports
     * success-shaped output for a well-formed email, whether or not an
     * account actually exists for it — Identity Toolkit's own
     * anti-enumeration behavior, not something this wrapper adds.
     *
     * @return array{success: true, error: null}|array{success: false, error: string}
     */
    public static function sendPasswordResetEmail(string $webApiKey, string $email): array
    {
        $data = self::rawPost('accounts:sendOobCode', $webApiKey, [
            'requestType' => 'PASSWORD_RESET',
            'email' => $email,
        ]);

        if ($data === null) {
            return ['success' => false, 'error' => 'network_error'];
        }

        if (isset($data['email'])) {
            return ['success' => true, 'error' => null];
        }

        return ['success' => false, 'error' => $data['error']['message'] ?? 'unknown_error'];
    }

    /**
     * One request per call — the same response is used for the success
     * shape (idToken/localId/refreshToken) AND, on failure, the
     * machine-readable reason Identity Toolkit gives (EMAIL_EXISTS,
     * INVALID_PASSWORD, EMAIL_NOT_FOUND...), so a caller can show
     * something more explicit than a generic failure via ErrorBanner,
     * without a second network round-trip just to find out why.
     *
     * @return array{idToken: string, localId: string, refreshToken: string, error: null}|array{idToken: null, localId: null, refreshToken: null, error: string}
     */
    private static function call(string $endpoint, string $webApiKey, string $email, string $password): array
    {
        return self::post($endpoint, $webApiKey, [
            'email' => $email,
            'password' => $password,
            'returnSecureToken' => true,
        ]);
    }

    /**
     * @param array<string, mixed> $body
     * @return array{idToken: string, localId: string, refreshToken: string, error: null}|array{idToken: null, localId: null, refreshToken: null, error: string}
     */
    private static function post(string $endpoint, string $webApiKey, array $body): array
    {
        $failure = ['idToken' => null, 'localId' => null, 'refreshToken' => null, 'error' => null];

        $data = self::rawPost($endpoint, $webApiKey, $body);

        if ($data === null) {
            return [...$failure, 'error' => 'network_error'];
        }

        if (isset($data['idToken'], $data['localId'])) {
            return [
                'idToken' => $data['idToken'],
                'localId' => $data['localId'],
                'refreshToken' => $data['refreshToken'] ?? '',
                'error' => null,
            ];
        }

        return [...$failure, 'error' => $data['error']['message'] ?? 'unknown_error'];
    }

    /**
     * Every Identity Toolkit endpoint's response has its OWN shape
     * (accounts:signUp/signInWithPassword/signInWithIdp return idToken/
     * localId; accounts:sendOobCode returns email/kind instead, no
     * idToken at all) — this is the shared raw HTTP call every public
     * method here goes through, each interpreting the decoded response
     * its own way rather than forcing one fixed shape onto all of them.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>|null null on any network/transport
     *     failure — an error Identity Toolkit itself reported still
     *     decodes fine here, it's just an array with an `error` key.
     */
    private static function rawPost(string $endpoint, string $webApiKey, array $body): ?array
    {
        try {
            $response = file_get_contents(
                "https://identitytoolkit.googleapis.com/v1/{$endpoint}?key={$webApiKey}",
                false,
                stream_context_create([
                    'http' => [
                        'method' => 'POST',
                        'header' => "Content-Type: application/json\r\n",
                        'content' => json_encode($body, JSON_THROW_ON_ERROR),
                        'timeout' => 10,
                        'ignore_errors' => true,
                    ],
                ]),
            );

            if ($response === false) {
                return null;
            }

            $data = json_decode($response, true);

            return is_array($data) ? $data : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
