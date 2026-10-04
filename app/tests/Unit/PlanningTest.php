<?php

namespace App\Tests\Unit;

use App\Controller\PlanningController;
use App\Entity\Aptitude;
use App\Entity\PlanningCreneau;
use App\Entity\PlanningSemaine;
use App\Entity\Role;
use App\Entity\User;
use App\Enum\Activite;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

class PlanningTest extends TestCase
{
    public function testLundiDeRamenePourToutJourDeLaSemaine(): void
    {
        foreach (['2026-09-28', '2026-10-01', '2026-10-04'] as $jour) {
            $this->assertSame('2026-09-28', PlanningController::lundiDe(new \DateTimeImmutable($jour.' 15:30'))->format('Y-m-d'));
        }
        $this->assertSame('2026-10-05', PlanningController::lundiDe(new \DateTimeImmutable('2026-10-05'))->format('Y-m-d'));
    }

    public function testRolesSymfonyDerivesDesRolesUtilisateur(): void
    {
        $user = new User();
        $this->assertSame(['ROLE_USER'], $user->getRoles());

        $user->addUserRole((new Role())->setCode(Role::ADHERENT)->setLibelle('Adhérent'));
        $user->addUserRole((new Role())->setCode(Role::ADMIN)->setLibelle('Administrateur'));

        $this->assertSame(['ROLE_USER', 'ROLE_ADHERENT', 'ROLE_ADMIN'], $user->getRoles());
        $this->assertTrue($user->hasRole(Role::ADMIN));
        $this->assertFalse($user->hasRole(Role::MONITEUR));
    }

    public function testSemaineExigeUnLundiEtUnDpMoniteur(): void
    {
        $violations = function (PlanningSemaine $semaine): int {
            $n = 0;
            $builder = $this->createStub(ConstraintViolationBuilderInterface::class);
            $builder->method('atPath')->willReturnSelf();
            $builder->method('addViolation')->willReturnCallback(function () use (&$n) { ++$n; });
            $context = $this->createStub(ExecutionContextInterface::class);
            $context->method('buildViolation')->willReturn($builder);
            $semaine->validate($context);

            return $n;
        };

        $semaine = (new PlanningSemaine())->setLundi(new \DateTimeImmutable('2026-10-02')); // vendredi
        $this->assertSame(1, $violations($semaine));

        $user = (new User())->addUserRole((new Role())->setCode(Role::ADHERENT)->setLibelle('Adhérent'));
        $semaine->setLundi(new \DateTimeImmutable('2026-09-28'))->setDp($user);
        $this->assertSame(1, $violations($semaine));

        $user->addUserRole((new Role())->setCode(Role::MONITEUR)->setLibelle('Moniteur'));
        $this->assertSame(0, $violations($semaine));
    }

    public function testCloneDeCreneauConserveLesAptitudesSansLesPartager(): void
    {
        $semaine = new PlanningSemaine();
        $creneau = (new PlanningCreneau())->setActivites([Activite::Pmt, Activite::Bloc])->addAptitude((new Aptitude())->setCode('N1'));
        $semaine->addCreneau($creneau);

        $copie = clone $creneau;
        $copie->addAptitude((new Aptitude())->setCode('N2'));

        $this->assertNull($copie->getSemaine());
        $this->assertCount(2, $copie->getAptitudes());
        $this->assertCount(1, $creneau->getAptitudes());
    }
}
