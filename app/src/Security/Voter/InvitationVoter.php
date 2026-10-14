<?php

namespace App\Security\Voter;

use App\Entity\InvitationFosse;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Un membre gère une invitation s'il est ADMIN ou s'il partage au moins un rôle avec elle.
 *
 * @extends Voter<string, InvitationFosse>
 */
class InvitationVoter extends Voter
{
    public const GERER = 'INVITATION_GERER';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::GERER === $attribute && $subject instanceof InvitationFosse;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        return $user instanceof User && $subject->estGerablePar($user);
    }
}
