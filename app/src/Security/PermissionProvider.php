<?php

namespace App\Security;

use App\Repository\RolePermissionRepository;

/** Rôles qui accordent chaque permission, lus en base une seule fois par requête. */
class PermissionProvider
{
    /** @var array<string, list<string>>|null */
    private ?array $roles = null;

    public function __construct(private readonly RolePermissionRepository $repository)
    {
    }

    /** @return list<string> Codes de rôles (hors ADMIN) accordant la permission */
    public function rolesPour(string $permission): array
    {
        $this->roles ??= $this->repository->rolesParPermission();

        return $this->roles[$permission] ?? [];
    }
}
