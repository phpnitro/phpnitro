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

        $payload = json_encode($body, JSON_THROW_ON_ERROR);

        try {
            $response = file_get_contents(
                "https://identitytoolkit.googleapis.com/v1/{$endpoint}?key={$webApiKey}",
                false,
                stream_context_create([
                    'http' => [
                        'method' => 'POST',
                        'header' => "Content-Type: application/json\r\n",
                        'content' => $payload,
                        'timeout' => 10,
                        'ignore_errors' => true,
                    ],
                ]),
            );

            if ($response === false) {
                return [...$failure, 'error' => 'network_error'];
            }

            $data = json_decode($response, true);

            if (isset($data['idToken'], $data['localId'])) {
                return [
                    'idToken' => $data['idToken'],
                    'localId' => $data['localId'],
                    'refreshToken' => $data['refreshToken'] ?? '',
                    'error' => null,
                ];
            }

            return [...$failure, 'error' => $data['error']['message'] ?? 'unknown_error'];
        } catch (\Throwable) {
            return [...$failure, 'error' => 'network_error'];
        }
    }
}
