<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;


class LoginController
{
    public function __construct(
        private UserProviderInterface $userProvider,
        private JWTTokenManagerInterface $jwtManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return new JsonResponse(['error' => 'Format JSON invalide'], JsonResponse::HTTP_BAD_REQUEST);
            }
            if (empty($data['email']) || empty($data['password'])) {
                return new JsonResponse(['error' => 'Email et mot de passe requis'], JsonResponse::HTTP_BAD_REQUEST);
            }

            // 1. Charger l'utilisateur ou lever UserNotFound
            try {
                $user = $this->userProvider->loadUserByIdentifier($data['email']);
            } catch (UserNotFoundException $e) {
                throw new BadCredentialsException('Identifiants invalides.');
            }
            

            // 2. S'assurer qu'il s'agit bien d'un PasswordAuthenticatedUserInterface
            if (!$user instanceof PasswordAuthenticatedUserInterface) {
                throw new \LogicException('L\'utilisateur ne gère pas de mot de passe.');
            }

            // 3. Vérifier le mot de passe
            if (!$this->passwordHasher->isPasswordValid($user, $data['password'])) {
                throw new BadCredentialsException('Identifiants invalides.');
            }

            // 4. Générer le token
            $token = $this->jwtManager->create($user);

            // Si tu veux renvoyer l'email, utilise getUserIdentifier() ou caste en App\Entity\User
            $email = $user instanceof User
                ? $user->getEmail()
                : $user->getUserIdentifier();

            return new JsonResponse([
                'token' => $token,
                'user'  => [
                    'email' => $email,
                    'roles' => $user->getRoles(),
                ],
            ]);
        } catch (BadCredentialsException $e) {
            return new JsonResponse(['error' => $e->getMessage()], JsonResponse::HTTP_UNAUTHORIZED);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Une erreur est survenue'], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
