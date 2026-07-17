<?php

namespace Engine\Firebase;

/**
 * Sends a push via FCM's HTTP v1 API (POST .../messages:send), which
 * requires a service-account bearer token — see GoogleServiceAccount.
 *
 * This is meant to run on a real, separate application server — NEVER
 * from the PHP embedded on-device (WebAppInterface/PhpServer): a service
 * account's private key is a genuine server credential, and every device
 * running the app embeds and runs its own PHP process, so it must never
 * ship inside an APK. lib/backend/src/Repository/FcmTokenRepository.php
 * (token storage) is the on-device half; this class is the other half,
 * meant to be called from your own hosted backend.
 *
 * Not tested against a real Firebase project in this environment (no
 * service account available here) — same confidence tier as Mapbox/
 * Google Maps: implemented from FCM's current published API docs.
 */
final class FirebaseMessaging
{
    public static function send(
        string $serviceAccountJsonPath,
        string $projectId,
        string $deviceToken,
        string $title,
        string $body,
    ): bool {
        $accessToken = GoogleServiceAccount::accessToken(
            $serviceAccountJsonPath,
            'https://www.googleapis.com/auth/firebase.messaging',
        );

        if ($accessToken === null) {
            return false;
        }

        $payload = json_encode([
            'message' => [
                'token' => $deviceToken,
                'notification' => ['title' => $title, 'body' => $body],
            ],
        ], JSON_THROW_ON_ERROR);

        try {
            $response = file_get_contents(
                "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send",
                false,
                stream_context_create([
                    'http' => [
                        'method' => 'POST',
                        'header' => "Authorization: Bearer {$accessToken}\r\nContent-Type: application/json\r\n",
                        'content' => $payload,
                        'timeout' => 10,
                        'ignore_errors' => true,
                    ],
                ]),
            );

            if ($response === false) {
                return false;
            }

            $data = json_decode($response, true);

            return isset($data['name']);
        } catch (\Throwable) {
            return false;
        }
    }
}
