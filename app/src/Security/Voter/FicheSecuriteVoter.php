<?php

namespace App\Security\Voter;

use App\Entity\InvitationFosse;
use App\Entity\Role;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * La fiche de sécurité d'une invitation est ouverte par son DP (responsable de l'invitation) et par les administrateurs.
 *
 * @extends Voter<string, InvitationFosse>
 */
class FicheSecuriteVoter extends Voter
{
    public const OUVRIR = 'FICHE_SECU_OUVRIR';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::OUVRIR === $attribute && $subject instanceof InvitationFosse;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return $user->hasRole(Role::ADMIN) || (null !== $subject->getDp() && $subject->getDp() === $user);
    }
}
