<?php

namespace Engine\Firebase;

/**
 * Minimal Firestore REST client (get/set one document) — no SDK, uses
 * GoogleServiceAccount for auth like FirebaseMessaging. An ALTERNATIVE to
 * Database::connection() (Doctrine DBAL/SQL), not plugged into it:
 * Firestore is a document store, not a DBAL driver, so a repository that
 * wants Firestore instead of SQL calls this class directly rather than
 * Database::connection().
 *
 * Only get/set are implemented — enough to prove the REST/auth shape
 * works; a real query() (filters, ordering) is a bigger surface Firestore's
 * structured-query REST format warrants its own dedicated pass. Not tested
 * against a real Firebase project in this environment (no service account
 * available here) — same confidence tier as Mapbox/Google Maps.
 */
final class Firestore
{
    /**
     * @return array<string, mixed>|null null if the document doesn't
     *      exist or the request failed
     */
    public static function get(
        string $serviceAccountJsonPath,
        string $projectId,
        string $collection,
        string $documentId,
    ): ?array {
        $accessToken = GoogleServiceAccount::accessToken(
            $serviceAccountJsonPath,
            'https://www.googleapis.com/auth/datastore',
        );

        if ($accessToken === null) {
            return null;
        }

        $url = self::documentUrl($projectId, $collection, $documentId);

        try {
            $response = file_get_contents($url, false, stream_context_create([
                'http' => [
                    'header' => "Authorization: Bearer {$accessToken}\r\n",
                    'timeout' => 10,
                    'ignore_errors' => true,
                ],
            ]));

            if ($response === false) {
                return null;
            }

            $data = json_decode($response, true);

            if (!isset($data['fields'])) {
                return null;
            }

            return self::decodeFields($data['fields']);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $fields
     */
    public static function set(
        string $serviceAccountJsonPath,
        string $projectId,
        string $collection,
        string $documentId,
        array $fields,
    ): bool {
        $accessToken = GoogleServiceAccount::accessToken(
            $serviceAccountJsonPath,
            'https://www.googleapis.com/auth/datastore',
        );

        if ($accessToken === null) {
            return false;
        }

        $url = self::documentUrl($projectId, $collection, $documentId);
        $payload = json_encode(['fields' => self::encodeFields($fields)], JSON_THROW_ON_ERROR);

        try {
            $response = file_get_contents($url, false, stream_context_create([
                'http' => [
                    'method' => 'PATCH',
                    'header' => "Authorization: Bearer {$accessToken}\r\nContent-Type: application/json\r\n",
                    'content' => $payload,
                    'timeout' => 10,
                    'ignore_errors' => true,
                ],
            ]));

            if ($response === false) {
                return false;
            }

            $data = json_decode($response, true);

            return isset($data['fields']) || isset($data['name']);
        } catch (\Throwable) {
            return false;
        }
    }

    private static function documentUrl(string $projectId, string $collection, string $documentId): string
    {
        $collection = rawurlencode($collection);
        $documentId = rawurlencode($documentId);

        return "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/{$collection}/{$documentId}";
    }

    /**
     * Firestore's REST format wraps every value in a type tag
     * ({"stringValue": "x"}, {"integerValue": "3"}...) — only the types a
     * simple key/value document needs are handled (string/int/float/bool),
     * not arrays/maps/timestamps/references.
     *
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    private static function encodeFields(array $fields): array
    {
        $encoded = [];

        foreach ($fields as $key => $value) {
            $encoded[$key] = match (true) {
                is_bool($value) => ['booleanValue' => $value],
                is_int($value) => ['integerValue' => (string) $value],
                is_float($value) => ['doubleValue' => $value],
                default => ['stringValue' => (string) $value],
            };
        }

        return $encoded;
    }

    /**
     * @param array<string, array<string, mixed>> $fields
     * @return array<string, mixed>
     */
    private static function decodeFields(array $fields): array
    {
        $decoded = [];

        foreach ($fields as $key => $typed) {
            $decoded[$key] = match (true) {
                isset($typed['booleanValue']) => (bool) $typed['booleanValue'],
                isset($typed['integerValue']) => (int) $typed['integerValue'],
                isset($typed['doubleValue']) => (float) $typed['doubleValue'],
                isset($typed['stringValue']) => $typed['stringValue'],
                default => null,
            };
        }

        return $decoded;
    }
}
