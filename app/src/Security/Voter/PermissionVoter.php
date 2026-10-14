<?php

namespace App\Security\Voter;

use App\Entity\Role;
use App\Entity\User;
use App\Security\Permission;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/** @extends Voter<string, mixed> */
class PermissionVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return array_key_exists($attribute, Permission::ROLES);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        return $user instanceof User && self::accorde($user, $attribute);
    }

    public static function accorde(User $user, string $permission): bool
    {
        if ($user->hasRole(Role::ADMIN)) {
            return true;
        }

        foreach (Permission::rolesPour($permission) as $code) {
            if ($user->hasRole($code)) {
                return true;
            }
        }

        return false;
    }
}
