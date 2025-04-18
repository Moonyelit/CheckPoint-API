<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException as ExceptionTooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Exception\TooManyLoginAttemptsAuthenticationException;

class CustomAuthenticationFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): JsonResponse
    {
        if ($exception instanceof ExceptionTooManyLoginAttemptsAuthenticationException) {
            return new JsonResponse([
                'code' => 429,
                'message' => 'Trop de tentatives de connexion. Réessaie dans une minute.',
            ], 429);
        }

        return new JsonResponse([
            'code' => 401,
            'message' => 'Identifiants incorrects',
        ], 401);
    }
}
