<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserRegisterProcessor implements ProcessorInterface
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $em
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): User
    {
        // Vérifie que l'entité est bien un utilisateur
        if (!$data instanceof User) {
            throw new \RuntimeException('Expected User');
        }

        // Vérifie que les mots de passe correspondent
        if ($data->getPassword() !== $data->getConfirmPassword()) {
            throw new \RuntimeException('Les mots de passe ne correspondent pas.');
        }

        // Hash le mot de passe avant de le sauvegarder
        $hashedPassword = $this->passwordHasher->hashPassword($data, $data->getPassword());
        $data->setPassword($hashedPassword);

        // Sauvegarde l'utilisateur dans la base de données
        $this->em->persist($data);
        $this->em->flush();

        return $data;
    }

}
