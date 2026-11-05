<?php

namespace App\Tests\Unit\Security;

use App\Entity\InvitationFosse;
use App\Entity\Role;
use App\Entity\User;
use App\Security\Voter\FicheSecuriteVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class FicheSecuriteVoterTest extends TestCase
{
    private function vote(User $user, InvitationFosse $invitation): int
    {
        return (new FicheSecuriteVoter())->vote(new UsernamePasswordToken($user, 'main', []), $invitation, [FicheSecuriteVoter::OUVRIR]);
    }

    private function utilisateur(string $role): User
    {
        return (new User())->addUserRole((new Role())->setCode($role)->setLibelle($role));
    }

    public function testLeDpEtLesAdministrateursOuvrentLaFiche(): void
    {
        $dp = $this->utilisateur(Role::MONITEUR);
        $invitation = (new InvitationFosse())->setDp($dp);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($dp, $invitation));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($this->utilisateur(Role::ADMIN), $invitation));
    }

    public function testUnAutreMoniteurOuMembreDuBureauNeLOuvrePas(): void
    {
        $invitation = (new InvitationFosse())->setDp($this->utilisateur(Role::MONITEUR));

        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->vote($this->utilisateur(Role::MONITEUR), $invitation));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->vote($this->utilisateur(Role::BUREAU), $invitation));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->vote($this->utilisateur(Role::MONITEUR), new InvitationFosse()));
    }
}
