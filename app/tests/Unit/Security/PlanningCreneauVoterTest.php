<?php

namespace App\Tests\Unit\Security;

use App\Entity\Aptitude;
use App\Entity\PlanningCreneau;
use App\Entity\User;
use App\Security\Permission;
use App\Security\Voter\PlanningCreneauVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class PlanningCreneauVoterTest extends TestCase
{
    private function aptitude(string $code): Aptitude
    {
        return (new Aptitude())->setCode($code);
    }

    private function creneau(Aptitude ...$aptitudes): PlanningCreneau
    {
        $creneau = new PlanningCreneau();
        foreach ($aptitudes as $a) {
            $creneau->addAptitude($a);
        }

        return $creneau;
    }

    private function vote(User $user, PlanningCreneau $creneau, bool $configActivites, bool $planningGroupes = true): int
    {
        $checker = $this->createStub(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturnCallback(
            fn (string $p) => match ($p) {
                Permission::CONFIG_ACTIVITES => $configActivites,
                Permission::PLANNING_GROUPES => $planningGroupes,
                default => false,
            }
        );

        return (new PlanningCreneauVoter($checker))->vote(
            new UsernamePasswordToken($user, 'main', []),
            $creneau,
            [PlanningCreneauVoter::GERER],
        );
    }

    public function testMoniteurNeGereQueSesGroupes(): void
    {
        [$a, $b, $c, $d] = [$this->aptitude('A'), $this->aptitude('B'), $this->aptitude('C'), $this->aptitude('D')];
        $m1 = (new User())->addAptitudesEncadree($a);
        $m2 = (new User())->addAptitudesEncadree($b)->addAptitudesEncadree($c);
        $m3 = (new User())->addAptitudesEncadree($a)->addAptitudesEncadree($c)->addAptitudesEncadree($d);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($m1, $this->creneau($a), false));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->vote($m1, $this->creneau($b), false));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($m2, $this->creneau($b), false));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($m2, $this->creneau($c), false));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->vote($m2, $this->creneau($a), false));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($m3, $this->creneau($a, $c), false));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($m3, $this->creneau($c, $b), false));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->vote($m3, $this->creneau($b), false));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($m2, $this->creneau($a, $b), false));
    }

    public function testCreneauSansAptitudeReserveAuxGestionnairesDuPlanning(): void
    {
        $moniteur = (new User())->addAptitudesEncadree($this->aptitude('A'));

        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->vote($moniteur, $this->creneau(), false));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($moniteur, $this->creneau(), true));
    }

    public function testSansLaPermissionLesGroupesNeSuffisentPas(): void
    {
        $a = $this->aptitude('A');
        $moniteur = (new User())->addAptitudesEncadree($a);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->vote($moniteur, $this->creneau($a), false, false));
    }
}
