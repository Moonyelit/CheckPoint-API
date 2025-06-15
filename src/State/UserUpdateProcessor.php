<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private UserPasswordHasherInterface $hasher,
        private EntityManagerInterface $em
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): User
    {
        // Vérifie que l'entité est bien un utilisateur
        if (!$data instanceof User) {
            throw new \LogicException('Mauvais type d\'entité');
        }

        // Récupérer l'utilisateur existant
        $existingUser = $this->em->getRepository(User::class)->find($data->getId());
        if (!$existingUser) {
            throw new \LogicException('Utilisateur non trouvé');
        }

        // Préserver les données sensibles
        $data->setPassword($existingUser->getPassword());
        $data->setEmail($existingUser->getEmail());
        $data->setEmailVerified($existingUser->isEmailVerified());

        // Re-hash le mot de passe si modifié
        if ($data->getPassword() && $data->getPassword() !== $existingUser->getPassword()) {
            $hashedPassword = $this->hasher->hashPassword($data, $data->getPassword());
            $data->setPassword($hashedPassword);
        }

        // Sauvegarde l'utilisateur dans la base de données
        $this->em->persist($data);
        $this->em->flush();

        return $data;
    }
}
