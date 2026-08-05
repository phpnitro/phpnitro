<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine\Supabase;

/**
 * A Supabase project's REST surface — PostgREST for table CRUD, GoTrue
 * for email/password auth — via plain HTTP, same file_get_contents()/
 * stream_context idiom Engine\Firebase\FirebaseAuth already uses. No
 * Supabase SDK exists for PHP the way supabase_flutter wraps Dart; this
 * is that wrapper, hand-written against Supabase's published REST API
 * instead of a client library.
 *
 * Realtime (Supabase's WebSocket channel) is NOT a separate method here
 * — it's a plain WebSocket endpoint
 * ("wss://{project}.supabase.co/realtime/v1/websocket?apikey=...&vsn=1.0.0"),
 * so Engine\Device\WebSocket::connectAction() already covers it with no
 * new capability needed; Supabase's own Realtime wire protocol (Phoenix
 * channels — join/leave messages, heartbeats) is a thin JSON message
 * shape on top of that raw connection, layered in your own PHP screen
 * logic rather than baked into a dedicated class here.
 *
 * Not tested against a real Supabase project in this environment (none
 * available here) — same confidence tier as FirebaseAuth/CloudFunctions.
 */
final class SupabaseClient
{
    public function __construct(
        private readonly string $projectUrl,
        private readonly string $apiKey,
        private readonly ?string $accessToken = null,
    ) {
    }

    /**
     * Returns a new client that sends this user's own JWT (from signIn()/
     * signUp()) instead of the anon key alone — needed for any table
     * whose Row Level Security policies check auth.uid(). Immutable,
     * same "returns a new instance" idiom as Engine\Math\Decimal.
     */
    public function withAccessToken(string $accessToken): self
    {
        return new self($this->projectUrl, $this->apiKey, $accessToken);
    }

    /**
     * @param array<string, int|float|string|bool> $filters column => value, combined with AND, always an equality match ("eq.")
     * @return array<int, array<string, mixed>>
     */
    public function select(string $table, string $columns = '*', array $filters = []): array
    {
        $query = ['select' => $columns];
        foreach ($filters as $column => $value) {
            $query[$column] = "eq.{$value}";
        }

        $result = $this->request('GET', "/rest/v1/{$table}?" . http_build_query($query));

        return array_is_list($result) ? $result : [];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int, array<string, mixed>> The inserted row(s), as PostgREST returns them.
     */
    public function insert(string $table, array $data): array
    {
        $result = $this->request('POST', "/rest/v1/{$table}", $data, ['Prefer: return=representation']);

        return array_is_list($result) ? $result : [];
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, int|float|string|bool> $filters column => value, combined with AND
     * @return array<int, array<string, mixed>> The updated row(s).
     */
    public function update(string $table, array $data, array $filters): array
    {
        $query = [];
        foreach ($filters as $column => $value) {
            $query[$column] = "eq.{$value}";
        }

        $result = $this->request('PATCH', "/rest/v1/{$table}?" . http_build_query($query), $data, ['Prefer: return=representation']);

        return array_is_list($result) ? $result : [];
    }

    /** @param array<string, int|float|string|bool> $filters column => value, combined with AND */
    public function delete(string $table, array $filters): bool
    {
        $query = [];
        foreach ($filters as $column => $value) {
            $query[$column] = "eq.{$value}";
        }

        return $this->rawRequest('DELETE', "/rest/v1/{$table}?" . http_build_query($query)) !== null;
    }

    /**
     * @return array{accessToken: string, refreshToken: string, userId: string, error: null}|array{accessToken: null, refreshToken: null, userId: null, error: string}
     */
    public function signUp(string $email, string $password): array
    {
        return $this->authRequest('/auth/v1/signup', $email, $password);
    }

    /** @return array{accessToken: string, refreshToken: string, userId: string, error: null}|array{accessToken: null, refreshToken: null, userId: null, error: string} */
    public function signIn(string $email, string $password): array
    {
        return $this->authRequest('/auth/v1/token?grant_type=password', $email, $password);
    }

    /**
     * @return array{accessToken: string, refreshToken: string, userId: string, error: null}|array{accessToken: null, refreshToken: null, userId: null, error: string}
     */
    private function authRequest(string $path, string $email, string $password): array
    {
        $failure = ['accessToken' => null, 'refreshToken' => null, 'userId' => null, 'error' => null];

        $response = $this->rawRequest('POST', $path, ['email' => $email, 'password' => $password]);

        if ($response === null) {
            return [...$failure, 'error' => 'network_error'];
        }

        if (isset($response['access_token'], $response['user']['id'])) {
            return [
                'accessToken' => $response['access_token'],
                'refreshToken' => $response['refresh_token'] ?? '',
                'userId' => $response['user']['id'],
                'error' => null,
            ];
        }

        return [...$failure, 'error' => $response['error_description'] ?? $response['msg'] ?? 'unknown_error'];
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, string> $extraHeaders
     * @return array<mixed>
     */
    private function request(string $method, string $path, array $data = [], array $extraHeaders = []): array
    {
        return $this->rawRequest($method, $path, $data, $extraHeaders) ?? [];
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, string> $extraHeaders
     * @return array<mixed>|null null on a network/transport failure — distinct from an empty array, which is a real "no rows" response.
     */
    private function rawRequest(string $method, string $path, array $data = [], array $extraHeaders = []): ?array
    {
        $headers = [
            "apikey: {$this->apiKey}",
            'Authorization: Bearer ' . ($this->accessToken ?? $this->apiKey),
            'Content-Type: application/json',
            ...$extraHeaders,
        ];

        $options = [
            'method' => $method,
            'header' => implode("\r\n", $headers) . "\r\n",
            'timeout' => 10,
            'ignore_errors' => true,
        ];

        if ($data !== []) {
            $options['content'] = json_encode($data, JSON_THROW_ON_ERROR);
        }

        try {
            $response = file_get_contents(
                rtrim($this->projectUrl, '/') . $path,
                false,
                stream_context_create(['http' => $options]),
            );
        } catch (\Throwable) {
            return null;
        }

        if ($response === false) {
            return null;
        }

        if ($response === '') {
            return [];
        }

        $decoded = json_decode($response, true);

        return is_array($decoded) ? $decoded : null;
    }
}
