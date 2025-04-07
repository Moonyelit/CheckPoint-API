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
        if (!$data instanceof User) {
            throw new \LogicException('Mauvais type d\'entité');
        }

        // Re-hash le mot de passe si modifié (optionnel)
        if ($data->getPassword()) {
            $hashedPassword = $this->hasher->hashPassword($data, $data->getPassword());
            $data->setPassword($hashedPassword);
        }

        $this->em->persist($data);
        $this->em->flush();

        return $data;
    }
}
