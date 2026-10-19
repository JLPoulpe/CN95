<?php

namespace App\Tests\Unit\Security;

use App\Entity\Role;
use App\Entity\User;
use App\Repository\RolePermissionRepository;
use App\Security\Permission;
use App\Security\PermissionProvider;
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

    /** @param array<string, list<string>> $configuration */
    private function voter(?array $configuration = null): PermissionVoter
    {
        $repository = $this->createStub(RolePermissionRepository::class);
        $repository->method('rolesParPermission')->willReturn($configuration ?? Permission::DEFAUTS);

        return new PermissionVoter(new PermissionProvider($repository));
    }

    public function testAdministrateurAToutesLesPermissions(): void
    {
        $voter = $this->voter([]);
        $admin = $this->utilisateur(Role::ADMIN, Role::ADHERENT);

        foreach (Permission::toutes() as $permission) {
            $this->assertTrue($voter->accorde($admin, $permission), $permission);
        }
    }

    public function testBureau(): void
    {
        $voter = $this->voter();
        $bureau = $this->utilisateur(Role::BUREAU, Role::ADHERENT);

        $this->assertTrue($voter->accorde($bureau, Permission::INVITATIONS));
        $this->assertTrue($voter->accorde($bureau, Permission::COMMUNICATION));
        $this->assertTrue($voter->accorde($bureau, Permission::ACTIVITES_EXTERNES));
        $this->assertTrue($voter->accorde($bureau, Permission::CONFIG_ACTIVITES));
        $this->assertFalse($voter->accorde($bureau, Permission::UTILISATEURS));
        $this->assertFalse($voter->accorde($bureau, Permission::CONFIG_OUTIL));
    }

    public function testMoniteurParDefaut(): void
    {
        $voter = $this->voter();
        $moniteur = $this->utilisateur(Role::MONITEUR, Role::ADHERENT);

        $this->assertTrue($voter->accorde($moniteur, Permission::ACTIVITES_EXTERNES));
        $this->assertFalse($voter->accorde($moniteur, Permission::INVITATIONS));
        $this->assertFalse($voter->accorde($moniteur, Permission::COMMUNICATION));
        $this->assertFalse($voter->accorde($moniteur, Permission::CONFIG_ACTIVITES));
        $this->assertFalse($voter->accorde($moniteur, Permission::UTILISATEURS));
        $this->assertFalse($voter->accorde($moniteur, Permission::CONFIG_OUTIL));
    }

    public function testGestionDesUtilisateursEtConfigurationDeLOutilReservesAuxAdministrateurs(): void
    {
        $voter = $this->voter();

        foreach ([$this->utilisateur(Role::BUREAU), $this->utilisateur(Role::MONITEUR)] as $membre) {
            $this->assertFalse($voter->accorde($membre, Permission::UTILISATEURS));
            $this->assertFalse($voter->accorde($membre, Permission::CONFIG_OUTIL));
        }
    }

    public function testAdherentNAucunePermission(): void
    {
        $voter = $this->voter();
        $adherent = $this->utilisateur(Role::ADHERENT);

        foreach (Permission::toutes() as $permission) {
            $this->assertFalse($voter->accorde($adherent, $permission), $permission);
        }
    }

    public function testLaConfigurationEnBaseFaitFoi(): void
    {
        $voter = $this->voter([Permission::COMMUNICATION => ['MONITEUR', 'ANIMATEUR']]);

        $this->assertTrue($voter->accorde($this->utilisateur(Role::MONITEUR), Permission::COMMUNICATION));
        $this->assertTrue($voter->accorde($this->utilisateur('ANIMATEUR'), Permission::COMMUNICATION));
        $this->assertFalse($voter->accorde($this->utilisateur(Role::BUREAU), Permission::COMMUNICATION));
        $this->assertFalse($voter->accorde($this->utilisateur(Role::MONITEUR), Permission::INVITATIONS));
    }

    public function testLaConfigurationEstLueUneSeuleFoisParRequete(): void
    {
        $repository = $this->createMock(RolePermissionRepository::class);
        $repository->expects($this->once())->method('rolesParPermission')->willReturn(Permission::DEFAUTS);
        $voter = new PermissionVoter(new PermissionProvider($repository));
        $bureau = $this->utilisateur(Role::BUREAU);

        $voter->accorde($bureau, Permission::INVITATIONS);
        $voter->accorde($bureau, Permission::COMMUNICATION);
    }
}
