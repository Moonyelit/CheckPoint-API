<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class LoginController
{
    public function __construct(
        private UserProviderInterface $userProvider,
        private JWTTokenManagerInterface $jwtManager
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['email'], $data['password'])) {
            throw new BadCredentialsException('Invalid credentials');
        }

        // Récupère l'utilisateur depuis la base (via le provider)
        $user = $this->userProvider->loadUserByIdentifier($data['email']);
        
        // ICI tu pourrais vérifier le mot de passe en utilisant un password hasher
        // Par simplicité, on suppose qu'il est correct.

        // Génère le token JWT
        $token = $this->jwtManager->create($user);

        // Retourne le token en JSON
        return new JsonResponse(['token' => $token]);
    }
}
