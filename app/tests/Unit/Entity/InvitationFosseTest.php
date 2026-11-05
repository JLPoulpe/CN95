<?php

namespace App\Tests\Unit\Entity;

use App\Entity\ActiviteExterne;
use App\Entity\InvitationFosse;
use App\Entity\Role;
use App\Entity\User;
use App\Enum\StatutReponseFosse;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

class InvitationFosseTest extends TestCase
{
    public function testLienValableLeJourDeLaFosseEtLeLendemain(): void
    {
        $aujourdhui = new \DateTimeImmutable('today');

        $dansUnJour = (new InvitationFosse())->setDate($aujourdhui->modify('+1 day'));
        $ceSoir = (new InvitationFosse())->setDate($aujourdhui);
        $hier = (new InvitationFosse())->setDate($aujourdhui->modify('-1 day'));

        $this->assertFalse($dansUnJour->isLienExpire());
        $this->assertFalse($ceSoir->isLienExpire(), 'Le jour de la fosse : lien valable');
        $this->assertFalse($hier->isLienExpire(), 'Le lendemain de la fosse : lien encore valable');
    }

    public function testLienExpireLeSurlendemainDeLaFosse(): void
    {
        $avantHier = (new InvitationFosse())->setDate((new \DateTimeImmutable('today'))->modify('-2 days'));

        $this->assertTrue($avantHier->isLienExpire());
    }

    public function testResponsableExigeSeulementSiActiviteLeNecessite(): void
    {
        $violations = function (InvitationFosse $invitation): int {
            $n = 0;
            $builder = $this->createStub(ConstraintViolationBuilderInterface::class);
            $builder->method('atPath')->willReturnSelf();
            $builder->method('addViolation')->willReturnCallback(function () use (&$n) { ++$n; });
            $context = $this->createStub(ExecutionContextInterface::class);
            $context->method('buildViolation')->willReturn($builder);
            $invitation->validate($context);

            return $n;
        };

        $activiteAvecResponsable = (new ActiviteExterne())->setCode('FOSSE')->setLibelle('Fosse')->setNecessiteResponsable(true);
        $activiteSansResponsable = (new ActiviteExterne())->setCode('SORTIE')->setLibelle('Sortie mer')->setNecessiteResponsable(false);

        $invitation = (new InvitationFosse())->setActivite($activiteAvecResponsable);
        $this->assertSame(1, $violations($invitation), 'Un responsable est requis quand l\'activité le nécessite.');

        $invitation->setActivite($activiteSansResponsable);
        $this->assertSame(0, $violations($invitation), 'Aucun responsable requis quand l\'activité ne le nécessite pas.');

        $nonMoniteur = (new User())->addUserRole((new Role())->setCode(Role::ADHERENT)->setLibelle('Adhérent'));
        $invitation->setActivite($activiteAvecResponsable)->setDp($nonMoniteur);
        $this->assertSame(1, $violations($invitation), 'Le responsable renseigné doit être moniteur.');

        $moniteur = (new User())->addUserRole((new Role())->setCode(Role::MONITEUR)->setLibelle('Moniteur'));
        $invitation->setDp($moniteur);
        $this->assertSame(0, $violations($invitation));
    }

    public function testGerableParUnAdministrateurOuUnMembreAyantUnRoleDeLInvitation(): void
    {
        $role = fn (string $code) => (new Role())->setCode($code)->setLibelle($code);
        $invitation = (new InvitationFosse())->addRole($role(Role::BUREAU));

        $admin = (new User())->addUserRole($role(Role::ADMIN));
        $bureau = (new User())->addUserRole($role(Role::BUREAU));
        $moniteur = (new User())->addUserRole($role(Role::MONITEUR));

        $this->assertTrue($invitation->estGerablePar($admin), 'Un administrateur gère toute invitation.');
        $this->assertTrue($invitation->estGerablePar($bureau), 'Un membre partageant un rôle gère l\'invitation.');
        $this->assertFalse($invitation->estGerablePar($moniteur), 'Sans rôle commun, pas de gestion.');
    }

    public function testUnMembreAvecPlusieursRolesVoitUneInvitationPartagee(): void
    {
        $role = fn (string $code) => (new Role())->setCode($code)->setLibelle($code);
        $invitation = (new InvitationFosse())->addRole($role(Role::MONITEUR))->addRole($role(Role::BUREAU));
        $membre = (new User())->addUserRole($role(Role::MONITEUR))->addUserRole($role(Role::BUREAU));

        $this->assertTrue($invitation->estGerablePar($membre));
    }

    public function testInscriptionListePrincipaleListeAttentePuisComplet(): void
    {
        $invitation = (new InvitationFosse())->setLimiteInscrits(30)->setLimiteAttente(10);

        $this->assertSame(StatutReponseFosse::Inscrit, $invitation->statutPourInscription(29, 0));
        $this->assertSame(StatutReponseFosse::ListeAttente, $invitation->statutPourInscription(30, 0));
        $this->assertSame(StatutReponseFosse::ListeAttente, $invitation->statutPourInscription(30, 9));
        $this->assertNull($invitation->statutPourInscription(30, 10));
    }

    public function testSansLimiteToutLeMondeEstInscritEtSansListeAttenteLaListePleineEstComplete(): void
    {
        $this->assertSame(StatutReponseFosse::Inscrit, (new InvitationFosse())->statutPourInscription(500, 0));
        $this->assertNull((new InvitationFosse())->setLimiteInscrits(5)->statutPourInscription(5, 0));
    }
}
