<?php

namespace App\Security\Voter;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

final class StatsUserGameVoter extends Voter
{
    // Actions possibles
    public const EDIT = 'POST_EDIT';
    public const VIEW = 'POST_VIEW';

    // Vérifie si l'attribut et le sujet sont supportés
    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::EDIT, self::VIEW])
            && $subject instanceof \App\Entity\StatsUserGame;
    }

    // Vérifie les permissions de l'utilisateur
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof UserInterface) {
            return false;
        }

        switch ($attribute) {
            case self::EDIT:
                // Logique pour modification
                break;

            case self::VIEW:
                // Logique pour visualisation
                break;
        }

        return false;
    }
}
