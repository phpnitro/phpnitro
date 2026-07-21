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
 * "Sign in with Slack" (OpenID Connect on top of Slack's OAuth) — needs
 * the openid/email/profile scopes, not Slack's older bot/workspace scopes.
 * UNVERIFIED — no real Slack App available in this environment.
 */
final class SlackSignIn extends OAuthProvider
{
    protected static function authorizeEndpoint(): string
    {
        return 'https://slack.com/openid/connect/authorize';
    }

    protected static function tokenEndpoint(): string
    {
        return 'https://slack.com/api/openid.connect.token';
    }

    protected static function userInfoEndpoint(): ?string
    {
        return 'https://slack.com/api/openid.connect.userInfo';
    }

    protected static function defaultScope(): string
    {
        return 'openid email profile';
    }

    protected static function normalize(array $tokenResponse, ?array $userInfoResponse): array
    {
        return [
            'id' => (string) ($userInfoResponse['sub'] ?? ''),
            'email' => $userInfoResponse['email'] ?? null,
            'name' => $userInfoResponse['name'] ?? null,
        ];
    }
}
