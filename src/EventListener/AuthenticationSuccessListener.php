<?php

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\Security\Core\User\UserInterface;
use App\Entity\User;

class AuthenticationSuccessListener
{
    public function onAuthenticationSuccessResponse(AuthenticationSuccessEvent $event)
    {
        $data = $event->getData();
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        // S'assurer que le token est présent
        if (!isset($data['token'])) {
            return;
        }

        // Construire la réponse complète
        $response = [
            'token' => $data['token'],
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'pseudo' => $user->getPseudo(),
                'emailVerified' => $user->isEmailVerified(),
                'profileImage' => $user->getProfileImage(),
                'roles' => $user->getRoles()
            ]
        ];

        $event->setData($response);
    }
} 