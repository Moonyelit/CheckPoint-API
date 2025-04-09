<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Psr\Log\LoggerInterface;

class MeProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private LoggerInterface $logger
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|null
    {
        // Récupère l'utilisateur connecté
        $user = $this->security->getUser();

        // Log l'identifiant de l'utilisateur ou indique qu'aucun utilisateur n'est connecté
        $this->logger->info('✅ Appel à /api/me', [
            'user' => $user ? $user->getUserIdentifier() : 'aucun utilisateur connecté'
        ]);

        // Retourne l'utilisateur connecté ou null
        return $user;
    }
}
