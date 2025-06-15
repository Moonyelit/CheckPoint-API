<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

class LoginController
{
    public function __construct(
        private UserProviderInterface $userProvider,
        private JWTTokenManagerInterface $jwtManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new JsonResponse(['error' => 'Format JSON invalide'], JsonResponse::HTTP_BAD_REQUEST);
        }
        if (empty($data['email']) || empty($data['password'])) {
            return new JsonResponse(['error' => 'Email et mot de passe requis'], JsonResponse::HTTP_BAD_REQUEST);
        }

        try {
            $user = $this->userProvider->loadUserByIdentifier($data['email']);
        } catch (UserNotFoundException $e) {
            throw new BadCredentialsException('Identifiants invalides.');
        }

        // Vérification des identifiants
        if (!$user instanceof PasswordAuthenticatedUserInterface) {
            throw new \LogicException("L'utilisateur ne gère pas de mot de passe.");
        }

        if (!$this->passwordHasher->isPasswordValid($user, $data['password'])) {
            throw new BadCredentialsException("Identifiants invalides.");
        }

        // Vérification que l'utilisateur est bien une instance de notre classe User
        if (!$user instanceof User) {
            throw new \LogicException("Type d'utilisateur invalide.");
        }

        // Génération du token JWT
        $token = $this->jwtManager->create($user);
        
        // Construction de la réponse avec toutes les données utilisateur nécessaires
        return new JsonResponse([
            'token' => $token, // Token JWT pour l'authentification
            'user'  => [
                'id' => $user->getId(), // ID unique de l'utilisateur
                'email' => $user->getEmail(), // Email de l'utilisateur
                'pseudo' => $user->getPseudo(), // Pseudo de l'utilisateur
                'emailVerified' => $user->isEmailVerified(), // Statut de vérification de l'email
                'profileImage' => $user->getProfileImage(), // URL de l'image de profil
                'roles' => $user->getRoles() // Rôles de l'utilisateur
            ]
        ]);
    }
}
