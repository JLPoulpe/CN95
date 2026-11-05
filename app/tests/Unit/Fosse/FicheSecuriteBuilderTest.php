<?php

namespace App\Tests\Unit\Fosse;

use App\Entity\Aptitude;
use App\Entity\InvitationFosse;
use App\Entity\ReponseFosse;
use App\Entity\Role;
use App\Entity\User;
use App\Enum\StatutReponseFosse;
use App\Fosse\FicheSecuriteBuilder;
use PHPUnit\Framework\TestCase;

class FicheSecuriteBuilderTest extends TestCase
{
    private InvitationFosse $invitation;
    private int $n = 0;

    protected function setUp(): void
    {
        $this->invitation = new InvitationFosse();
    }

    private function aptitude(string $code): Aptitude
    {
        return (new Aptitude())->setCode($code);
    }

    private function inscrire(bool $moniteur, ?Aptitude $preparee = null, StatutReponseFosse $statut = StatutReponseFosse::Inscrit, array $groupes = []): User
    {
        ++$this->n;
        $user = (new User())->setNom('Nom'.sprintf('%03d', $this->n))->setPrenom('P')->setAptitudePreparee($preparee);
        $user->addUserRole((new Role())->setCode($moniteur ? Role::MONITEUR : Role::ADHERENT)->setLibelle('r'));
        foreach ($groupes as $groupe) {
            $user->addAptitudesEncadree($groupe);
        }
        $reponse = new ReponseFosse($this->invitation, $user);
        $reponse->setStatut($statut);
        $this->invitation->getReponses()->add($reponse);

        return $user;
    }

    public function testUnePalanqueeParMoniteurDansLaLimiteDesInscrits(): void
    {
        for ($i = 0; $i < 4; ++$i) {
            $this->inscrire(true);
        }
        for ($i = 0; $i < 26; ++$i) {
            $this->inscrire(false);
        }

        $fiche = (new FicheSecuriteBuilder())->generer($this->invitation);

        $this->assertCount(4, $fiche->getPalanquees(), '30 inscrits mais 4 moniteurs : 4 palanquées.');
    }

    public function testPasDeMoniteurPasDePalanquee(): void
    {
        for ($i = 0; $i < 10; ++$i) {
            $this->inscrire(false);
        }

        $this->assertCount(0, (new FicheSecuriteBuilder())->generer($this->invitation)->getPalanquees());
    }

    public function testNombreDePalanqueesLimiteParLeNombreDInscrits(): void
    {
        for ($i = 0; $i < 4; ++$i) {
            $this->inscrire(true);
        }
        $this->inscrire(false);

        // 5 inscrits tiennent dans une seule palanquée de 5
        $this->assertCount(1, (new FicheSecuriteBuilder())->generer($this->invitation)->getPalanquees());
    }

    public function testLesAdherentsDesGroupesDuMoniteurRejoignentSaPalanquee(): void
    {
        $pe40 = $this->aptitude('PE40');
        $n3 = $this->aptitude('N3');
        $moniteur = $this->inscrire(true, null, StatutReponseFosse::Inscrit, [$pe40]);
        $autreMoniteur = $this->inscrire(true, null, StatutReponseFosse::Inscrit, [$n3]);
        $dePe40 = [];
        for ($i = 0; $i < 6; ++$i) {
            $dePe40[] = $this->inscrire(false, $pe40);
        }
        $deN3 = $this->inscrire(false, $n3);
        $sansGroupe = $this->inscrire(false, $this->aptitude('N1'));

        $builder = new FicheSecuriteBuilder();
        $fiche = $builder->generer($this->invitation);
        [$p1, $p2] = $fiche->getPalanquees()->toArray();

        $this->assertSame($moniteur, $p1->getChef());
        $this->assertCount(5, $p1->getMembres(), '1 chef + 4 accompagnants au plus.');
        $this->assertSame($autreMoniteur, $p2->getChef());
        $this->assertTrue($p2->contient($deN3));

        $restants = $builder->restants($fiche);
        $this->assertCount(2 + 1, $restants['adherents'], '2 adhérents de PE40 en surplus + 1 sans groupe.');
        $this->assertContains($sansGroupe, $restants['adherents']);
        $this->assertSame([], $restants['moniteurs']);
    }

    public function testMoniteursEnSurplusEtListeAttenteSontHorsPalanquees(): void
    {
        $this->inscrire(true);
        $this->inscrire(true);
        $this->inscrire(false);
        $attente = $this->inscrire(false, null, StatutReponseFosse::ListeAttente);

        $builder = new FicheSecuriteBuilder();
        $fiche = $builder->generer($this->invitation);
        $restants = $builder->restants($fiche);

        $this->assertCount(1, $fiche->getPalanquees(), '3 inscrits : une seule palanquée.');
        $this->assertCount(1, $restants['moniteurs'], 'Le second moniteur reste dans les restants.');
        $this->assertNotContains($attente, $restants['adherents']);
    }

    public function testLeDpAUneLaPremierePalanquee(): void
    {
        $premier = $this->inscrire(true);
        $dp = $this->inscrire(true);
        for ($i = 0; $i < 6; ++$i) {
            $this->inscrire(false);
        }
        $this->invitation->setDp($dp);

        $fiche = (new FicheSecuriteBuilder())->generer($this->invitation);

        $this->assertSame($dp, $fiche->getPalanquees()->first()->getChef());
        $this->assertNotSame($premier, $fiche->getPalanquees()->first()->getChef());
    }

    public function testSynchroniserRetireLesDesistes(): void
    {
        $moniteur = $this->inscrire(true);
        $adherent = $this->inscrire(false);
        $builder = new FicheSecuriteBuilder();
        $fiche = $builder->generer($this->invitation);
        $fiche->getPalanquees()->first()->ajouter($adherent);

        foreach ($this->invitation->getReponses() as $reponse) {
            if ($reponse->getUser() === $adherent) {
                $reponse->setStatut(StatutReponseFosse::Desistement);
            }
        }
        $builder->synchroniser($fiche);

        $this->assertFalse($fiche->estPlace($adherent));
        $this->assertTrue($fiche->estPlace($moniteur));
    }

    public function testDeplacementEtAvertissements(): void
    {
        $moniteur = $this->inscrire(true);
        $fiche = (new FicheSecuriteBuilder())->generer($this->invitation);
        $palanquee = $fiche->ajouterPalanquee();

        $this->assertSame([], $palanquee->getAvertissements(), 'Une palanquée vide n\'est pas signalée.');

        $adherents = [];
        for ($i = 0; $i < 6; ++$i) {
            $adherents[] = $this->inscrire(false);
            $palanquee->ajouter($adherents[$i]);
        }
        $this->assertCount(2, $palanquee->getAvertissements(), 'Sans moniteur et plus de 5 personnes.');

        $fiche->retirer($moniteur);
        $palanquee->ajouter($moniteur);
        $this->assertSame($moniteur, $palanquee->getChef());
        $this->assertFalse($fiche->getPalanquees()->first()->contient($moniteur));
    }
}
