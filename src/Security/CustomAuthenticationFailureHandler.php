<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException as ExceptionTooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Serializer\SerializerInterface;

class CustomAuthenticationFailureHandler implements AuthenticationFailureHandlerInterface
{
    private $serializer;

    public function __construct(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        if ($exception instanceof ExceptionTooManyLoginAttemptsAuthenticationException) {
            return new JsonResponse([
                'code' => 429,
                'message' => 'Trop de tentatives de connexion. Réessaie dans une minute.',
            ], 429);
        }

        $data = [
            'message' => 'Identifiants incorrects',
            'error' => $exception->getMessageKey(),
            'status' => Response::HTTP_UNAUTHORIZED
        ];

        return new JsonResponse($data, Response::HTTP_UNAUTHORIZED);
    }
}
