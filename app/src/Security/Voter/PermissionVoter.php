<?php

namespace App\Security\Voter;

use App\Entity\Role;
use App\Entity\User;
use App\Security\Permission;
use App\Security\PermissionProvider;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/** @extends Voter<string, mixed> */
class PermissionVoter extends Voter
{
    public function __construct(private readonly PermissionProvider $permissions)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return array_key_exists($attribute, Permission::LIBELLES);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        return $user instanceof User && $this->accorde($user, $attribute);
    }

    public function accorde(User $user, string $permission): bool
    {
        if ($user->hasRole(Role::ADMIN)) {
            return true;
        }

        foreach ($this->permissions->rolesPour($permission) as $code) {
            if ($user->hasRole($code)) {
                return true;
            }
        }

        return false;
    }
}
