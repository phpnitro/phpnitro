<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine\SocialAuth;

/**
 * UNVERIFIED — no real GitHub OAuth App available in this environment.
 */
final class GithubSignIn extends OAuthProvider
{
    protected static function authorizeEndpoint(): string
    {
        return 'https://github.com/login/oauth/authorize';
    }

    protected static function tokenEndpoint(): string
    {
        return 'https://github.com/login/oauth/access_token';
    }

    protected static function userInfoEndpoint(): ?string
    {
        return 'https://api.github.com/user';
    }

    protected static function defaultScope(): string
    {
        return 'read:user user:email';
    }

    protected static function userInfoHeaders(string $accessToken): array
    {
        return [
            'Authorization: Bearer ' . $accessToken,
            'User-Agent: PhpNitro',
        ];
    }

    protected static function normalize(array $tokenResponse, ?array $userInfoResponse): array
    {
        return [
            'id' => (string) ($userInfoResponse['id'] ?? ''),
            'email' => $userInfoResponse['email'] ?? null,
            'name' => $userInfoResponse['name'] ?? $userInfoResponse['login'] ?? null,
            'access_token' => $tokenResponse['access_token'] ?? null,
        ];
    }
}
