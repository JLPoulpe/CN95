<?php

namespace App\Entity;

use App\Repository\RolePermissionRepository;
use Doctrine\ORM\Mapping as ORM;

/** Accorde une permission (voir App\Security\Permission) à un rôle. Modifiable depuis l'administration. */
#[ORM\Entity(repositoryClass: RolePermissionRepository::class)]
#[ORM\Table(name: 'role_permission')]
#[ORM\UniqueConstraint(name: 'uniq_role_permission', columns: ['role_id', 'permission'])]
class RolePermission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Role $role;

    #[ORM\Column(length: 50)]
    private string $permission;

    public function __construct(Role $role, string $permission)
    {
        $this->role = $role;
        $this->permission = $permission;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRole(): Role
    {
        return $this->role;
    }

    public function getPermission(): string
    {
        return $this->permission;
    }
}
