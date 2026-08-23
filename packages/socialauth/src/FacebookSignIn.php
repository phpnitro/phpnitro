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
 * UNVERIFIED — no real Facebook App available in this environment.
 */
final class FacebookSignIn extends OAuthProvider
{
    protected static function authorizeEndpoint(): string
    {
        return 'https://www.facebook.com/v19.0/dialog/oauth';
    }

    protected static function tokenEndpoint(): string
    {
        return 'https://graph.facebook.com/v19.0/oauth/access_token';
    }

    protected static function userInfoEndpoint(): ?string
    {
        return 'https://graph.facebook.com/me?fields=id,name,email';
    }

    protected static function defaultScope(): string
    {
        return 'email public_profile';
    }

    protected static function normalize(array $tokenResponse, ?array $userInfoResponse): array
    {
        return [
            'id' => (string) ($userInfoResponse['id'] ?? ''),
            'email' => $userInfoResponse['email'] ?? null,
            'name' => $userInfoResponse['name'] ?? null,
            'access_token' => $tokenResponse['access_token'] ?? null,
        ];
    }
}
