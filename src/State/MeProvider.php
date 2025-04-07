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


        // On récupère l'utilisateur connecté
        $user = $this->security->getUser();

        // On log pour debug si un user est bien détecté ou non
        $this->logger->info('✅ Appel à /api/me', [
            'user' => $user ? $user->getUserIdentifier() : 'aucun utilisateur connecté'
        ]);

        // On retourne l'utilisateur si présent, sinon null
        return $user;
    }
}
