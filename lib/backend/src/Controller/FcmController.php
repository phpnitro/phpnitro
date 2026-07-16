<?php

namespace Backend\Controller;

use Backend\Repository\FcmTokenRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class FcmController
{
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $token = is_array($data) ? ($data['token'] ?? null) : null;

        if (!is_string($token) || $token === '') {
            return new JsonResponse(['error' => 'Missing "token" in request body.'], 400);
        }

        (new FcmTokenRepository())->register($token);

        return new JsonResponse(['status' => 'registered']);
    }

    public function count(Request $request): JsonResponse
    {
        return new JsonResponse(['tokens' => count((new FcmTokenRepository())->allTokens())]);
    }
}
