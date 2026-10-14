<?php

namespace App\Tests\Unit\Security;

use App\Entity\Role;
use App\Entity\User;
use App\Security\Permission;
use App\Security\Voter\PermissionVoter;
use PHPUnit\Framework\TestCase;

class PermissionVoterTest extends TestCase
{
    private function utilisateur(string ...$codes): User
    {
        $user = new User();
        foreach ($codes as $code) {
            $user->addUserRole((new Role())->setCode($code)->setLibelle($code));
        }

        return $user;
    }

    public function testAdministrateurAToutesLesPermissions(): void
    {
        $admin = $this->utilisateur(Role::ADMIN, Role::ADHERENT);

        foreach (array_keys(Permission::ROLES) as $permission) {
            $this->assertTrue(PermissionVoter::accorde($admin, $permission), $permission);
        }
    }

    public function testBureau(): void
    {
        $bureau = $this->utilisateur(Role::BUREAU, Role::ADHERENT);

        $this->assertTrue(PermissionVoter::accorde($bureau, Permission::INVITATIONS));
        $this->assertTrue(PermissionVoter::accorde($bureau, Permission::COMMUNICATION));
        $this->assertTrue(PermissionVoter::accorde($bureau, Permission::CONFIG_ACTIVITES));
        $this->assertFalse(PermissionVoter::accorde($bureau, Permission::UTILISATEURS));
        $this->assertFalse(PermissionVoter::accorde($bureau, Permission::CONFIG_OUTIL));
    }

    public function testMoniteur(): void
    {
        $moniteur = $this->utilisateur(Role::MONITEUR, Role::ADHERENT);

        $this->assertTrue(PermissionVoter::accorde($moniteur, Permission::INVITATIONS));
        $this->assertTrue(PermissionVoter::accorde($moniteur, Permission::ACTIVITES_EXTERNES));
        $this->assertTrue(PermissionVoter::accorde($moniteur, Permission::COMMUNICATION));
        $this->assertFalse(PermissionVoter::accorde($moniteur, Permission::CONFIG_ACTIVITES));
        $this->assertFalse(PermissionVoter::accorde($moniteur, Permission::UTILISATEURS));
        $this->assertFalse(PermissionVoter::accorde($moniteur, Permission::CONFIG_OUTIL));
    }

    public function testGestionDesUtilisateursEtConfigurationDeLOutilReservesAuxAdministrateurs(): void
    {
        $bureau = $this->utilisateur(Role::BUREAU);
        $moniteur = $this->utilisateur(Role::MONITEUR);

        foreach ([$bureau, $moniteur] as $membre) {
            $this->assertFalse(PermissionVoter::accorde($membre, Permission::UTILISATEURS));
            $this->assertFalse(PermissionVoter::accorde($membre, Permission::CONFIG_OUTIL));
        }
    }

    public function testAdherentNAucunePermission(): void
    {
        $adherent = $this->utilisateur(Role::ADHERENT);

        foreach (array_keys(Permission::ROLES) as $permission) {
            $this->assertFalse(PermissionVoter::accorde($adherent, $permission), $permission);
        }
    }
}
