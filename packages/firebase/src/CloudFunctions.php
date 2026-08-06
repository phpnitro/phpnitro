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
 * Calls a Firebase "callable" Cloud Function — same {"data": ...} request /
 * {"result": ...} response envelope Flutter's cloud_functions package's
 * httpsCallable() uses under the hood, so an existing callable function
 * written for a Flutter/JS client needs no changes to also serve this.
 *
 * $idToken is a Firebase Auth ID token (see FirebaseAuth::signIn()'s own
 * 'idToken' key) — required for a callable function that reads
 * context.auth on the function side, optional for a publicly-callable
 * one. This is a different credential than GoogleServiceAccount's
 * service-account access token (FirebaseMessaging's own auth): that one
 * authenticates YOUR SERVER to Google; this one identifies the END USER
 * to the function.
 *
 * Not tested against a real deployed Cloud Function in this environment
 * (no Firebase project available here) — same confidence tier as
 * FirebaseMessaging: implemented from Firebase's published callable-
 * function HTTP protocol, not verified against a live endpoint.
 */
final class CloudFunctions
{
    /**
     * @param array<string, mixed> $data
     * @return array<mixed>|mixed
     */
    public static function call(
        string $projectId,
        string $functionName,
        array $data = [],
        ?string $idToken = null,
        string $region = 'us-central1',
    ): mixed {
        $url = "https://{$region}-{$projectId}.cloudfunctions.net/{$functionName}";

        $header = "Content-Type: application/json\r\n";
        if ($idToken !== null) {
            $header .= "Authorization: Bearer {$idToken}\r\n";
        }

        $payload = json_encode(['data' => $data], JSON_THROW_ON_ERROR);

        $response = @file_get_contents($url, false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => $header,
                'content' => $payload,
                'timeout' => 15,
                'ignore_errors' => true,
            ],
        ]));

        if ($response === false) {
            throw new \RuntimeException("Impossible de joindre la fonction Cloud « {$functionName} ».");
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException("Réponse invalide de la fonction Cloud « {$functionName} ».");
        }

        if (isset($decoded['error'])) {
            $message = is_array($decoded['error']) ? ($decoded['error']['message'] ?? 'Erreur inconnue') : (string) $decoded['error'];

            throw new \RuntimeException("Erreur de la fonction Cloud « {$functionName} » : {$message}");
        }

        return $decoded['result'] ?? $decoded;
    }
}
