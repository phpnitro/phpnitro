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
 * Microsoft identity platform (Azure AD / personal Microsoft accounts),
 * "common" tenant so both work. UNVERIFIED — no real Azure app
 * registration available in this environment.
 */
final class MicrosoftSignIn extends OAuthProvider
{
    protected static function authorizeEndpoint(): string
    {
        return 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize';
    }

    protected static function tokenEndpoint(): string
    {
        return 'https://login.microsoftonline.com/common/oauth2/v2.0/token';
    }

    protected static function userInfoEndpoint(): ?string
    {
        return 'https://graph.microsoft.com/v1.0/me';
    }

    protected static function defaultScope(): string
    {
        return 'openid email profile User.Read';
    }

    protected static function normalize(array $tokenResponse, ?array $userInfoResponse): array
    {
        return [
            'id' => (string) ($userInfoResponse['id'] ?? ''),
            'email' => $userInfoResponse['mail'] ?? $userInfoResponse['userPrincipalName'] ?? null,
            'name' => $userInfoResponse['displayName'] ?? null,
            'access_token' => $tokenResponse['access_token'] ?? null,
        ];
    }
}
