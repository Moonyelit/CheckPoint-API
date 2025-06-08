<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class UserRegisterProcessor implements ProcessorInterface
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $em,
        private ValidatorInterface $validator
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): User
    {
        // Vérifie que l'entité est bien un utilisateur
        if (!$data instanceof User) {
            throw new \RuntimeException('Expected User');
        }

        // Sanitisation et validation des données d'entrée
        $this->sanitizeAndValidateUserData($data);

        // Vérifie que les mots de passe correspondent
        if ($data->getPassword() !== $data->getConfirmPassword()) {
            throw new BadRequestHttpException('Les mots de passe ne correspondent pas.');
        }

        // Validation supplémentaire du mot de passe
        $this->validatePassword($data->getPassword());

        // Validation du pseudo
        $this->validatePseudo($data->getPseudo());

        // Validation de l'email
        $this->validateEmail($data->getEmail());

        // Utiliser le validator Symfony pour les contraintes de l'entité
        $violations = $this->validator->validate($data);
        if (count($violations) > 0) {
            $errorMessages = [];
            foreach ($violations as $violation) {
                $errorMessages[] = $violation->getMessage();
            }
            throw new BadRequestHttpException('Données invalides: ' . implode(', ', $errorMessages));
        }

        // Hash le mot de passe avant de le sauvegarder
        $hashedPassword = $this->passwordHasher->hashPassword($data, $data->getPassword());
        $data->setPassword($hashedPassword);

        // Nettoyer le confirmPassword avant la sauvegarde
        $data->setConfirmPassword(null);

        // Sauvegarde l'utilisateur dans la base de données
        $this->em->persist($data);
        $this->em->flush();

        return $data;
    }

    private function sanitizeAndValidateUserData(User $user): void
    {
        // Sanitisation du pseudo
        $pseudo = trim($user->getPseudo());
        $pseudo = filter_var($pseudo, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
        $user->setPseudo($pseudo);

        // Sanitisation de l'email
        $email = trim(strtolower($user->getEmail()));
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        $user->setEmail($email);
    }

    private function validatePassword(string $password): void
    {
        if (strlen($password) < 8) {
            throw new BadRequestHttpException('Le mot de passe doit contenir au moins 8 caractères.');
        }

        if (!preg_match('/[A-Z]/', $password)) {
            throw new BadRequestHttpException('Le mot de passe doit contenir au moins une majuscule.');
        }

        if (!preg_match('/[a-z]/', $password)) {
            throw new BadRequestHttpException('Le mot de passe doit contenir au moins une minuscule.');
        }

        if (!preg_match('/\d/', $password)) {
            throw new BadRequestHttpException('Le mot de passe doit contenir au moins un chiffre.');
        }

        // Vérification contre les mots de passe courants
        $commonPasswords = [
            'password', '123456789', 'qwerty', 'azerty', 'admin',
            'password123', '123456', '12345678', 'motdepasse'
        ];

        if (in_array(strtolower($password), $commonPasswords)) {
            throw new BadRequestHttpException('Ce mot de passe est trop commun, veuillez en choisir un autre.');
        }
    }

    private function validatePseudo(string $pseudo): void
    {
        if (strlen($pseudo) < 3) {
            throw new BadRequestHttpException('Le pseudo doit contenir au moins 3 caractères.');
        }

        if (strlen($pseudo) > 15) {
            throw new BadRequestHttpException('Le pseudo ne peut pas dépasser 15 caractères.');
        }

        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $pseudo)) {
            throw new BadRequestHttpException('Le pseudo ne peut contenir que des lettres, chiffres, tirets et underscores.');
        }

        // Vérification contre les pseudos inappropriés
        $forbiddenWords = ['admin', 'root', 'test', 'user', 'moderator', 'mod', 'administrator'];
        if (in_array(strtolower($pseudo), $forbiddenWords)) {
            throw new BadRequestHttpException('Ce pseudo n\'est pas autorisé.');
        }
    }

    private function validateEmail(string $email): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new BadRequestHttpException('Format d\'email invalide.');
        }

        // Vérification contre les domaines d'email temporaires/jetables
        $disposableDomains = [
            '10minutemail.com', 'tempmail.org', 'guerrillamail.com',
            'mailinator.com', 'throwaway.email'
        ];

        $emailDomain = substr(strrchr($email, "@"), 1);
        if (in_array($emailDomain, $disposableDomains)) {
            throw new BadRequestHttpException('Les adresses email temporaires ne sont pas autorisées.');
        }
    }
}
