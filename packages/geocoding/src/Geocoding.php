<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine\Geocoding;

/**
 * Forward (address -> coordinates) and reverse (coordinates -> address)
 * geocoding via OpenStreetMap's Nominatim — no API key, same tile
 * provider NativeWidgetsMapsScreen/osmdroid already pull from, so this
 * adds no new third-party account/attribution burden. Engine\Device's
 * own "locate" action gives a device's current coordinates; this is for
 * turning free-text addresses into coordinates (or back), which needs an
 * actual geocoding service, not GPS.
 *
 * Server-side only — meant to run from your own backend or from PHP
 * running on-device, same as any other outbound HTTPS call this
 * framework makes (translateText, GoogleTranslate, etc.). Nominatim's
 * usage policy caps free usage at 1 request/second and requires a real
 * identifying User-Agent (set below) — a high-volume app should run its
 * own Nominatim instance or use a paid geocoding provider instead.
 */
final class Geocoding
{
    private const BASE_URL = 'https://nominatim.openstreetmap.org';

    /**
     * @return array<int, array{lat: float, lon: float, displayName: string}>
     */
    public static function forward(string $address, int $limit = 5): array
    {
        $url = self::BASE_URL . '/search?' . http_build_query([
            'q' => $address,
            'format' => 'json',
            'limit' => $limit,
        ]);

        $results = self::request($url);

        return array_map(
            static fn (array $row): array => [
                'lat' => (float) $row['lat'],
                'lon' => (float) $row['lon'],
                'displayName' => $row['display_name'],
            ],
            $results,
        );
    }

    /**
     * @return array{displayName: string, address: array<string, string>}|null
     */
    public static function reverse(float $latitude, float $longitude): ?array
    {
        $url = self::BASE_URL . '/reverse?' . http_build_query([
            'lat' => $latitude,
            'lon' => $longitude,
            'format' => 'json',
        ]);

        $result = self::request($url);

        if (!isset($result['display_name'])) {
            return null;
        }

        return [
            'displayName' => $result['display_name'],
            'address' => $result['address'] ?? [],
        ];
    }

    /** @return array<mixed> */
    private static function request(string $url): array
    {
        $response = @file_get_contents($url, false, stream_context_create([
            'http' => [
                'method' => 'GET',
                // Nominatim's usage policy REQUIRES a real identifying
                // User-Agent — a missing/generic one gets rate-limited
                // harder or blocked outright, same reason osmdroid's own
                // Configuration.userAgentValue is set for map tile
                // requests (NativeRenderPocActivity.kt's onCreate()).
                'header' => "User-Agent: PhpNitro-App/1.0 (+https://phpnitro.dev)\r\n",
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]));

        if ($response === false) {
            throw new \RuntimeException('Impossible de joindre le service de géocodage Nominatim.');
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Réponse invalide du service de géocodage Nominatim.');
        }

        return $data;
    }
}
