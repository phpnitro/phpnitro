<?php

namespace Backend\Controller;

use Backend\Repository\FcmTokenRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class FcmController
{
    public function register(Request $request): JsonResponse
    {
        $token = json_decode($request->getContent(), true)['token'] ?? null;

        if (!is_string($token) || $token === '') {
            return new JsonResponse(['error' => 'token is required'], 400);
        }

        (new FcmTokenRepository())->register($token);

        return new JsonResponse(['status' => 'ok']);
    }
}
