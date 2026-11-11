<?php

namespace App\Tests\Unit;

use App\Entity\Activite;
use App\Entity\Aptitude;
use App\Entity\LieuActivite;
use App\Entity\PlanningCreneau;
use App\Entity\PlanningSemaine;
use App\Enum\Jour;
use App\Planning\PlanningCsvImporter;
use App\Repository\ActiviteRepository;
use App\Repository\AptitudeRepository;
use App\Repository\LieuActiviteRepository;
use App\Repository\PlanningSemaineRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class PlanningCsvImporterTest extends TestCase
{
    public function testGroupeParActiviteEtAffecteLesLieux(): void
    {
        $apt = fn (string $c) => (new Aptitude())->setCode($c);
        $aptitudes = ['PN1' => $apt('PN1'), 'PE40' => $apt('PE40')];
        $aRepo = $this->createStub(AptitudeRepository::class);
        $aRepo->method('findOneBy')->willReturnCallback(fn (array $c) => $aptitudes[$c['code']] ?? null);

        $acRepo = $this->createStub(ActiviteRepository::class);
        $acRepo->method('findAll')->willReturn([
            (new Activite())->setCode('BLOC')->setLibelle('Séance bloc'),
            (new Activite())->setCode('PMT')->setLibelle('Séance PMT'),
            (new Activite())->setCode('AUTRES')->setLibelle('Autres'),
        ]);

        $lieux = [];
        foreach ([['Grand bassin', 1], ['Grand bassin', 2], ['Grand bassin', 3], ['Grand bassin', 4], ['Grand bassin', 5], ['Grand bassin', 6]] as [$g, $n]) {
            $lieux[] = (new LieuActivite())->setGroupe($g)->setNom('Ligne '.$n);
        }
        $lRepo = $this->createStub(LieuActiviteRepository::class);
        $lRepo->method('findAll')->willReturn($lieux);

        $sRepo = $this->createStub(PlanningSemaineRepository::class);
        $sRepo->method('findOneBy')->willReturn(null);
        $persistes = [];
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function ($o) use (&$persistes) { $persistes[] = $o; });

        $f = tempnam(sys_get_temp_dir(), 'plan');
        file_put_contents($f, "\xEF\xBB\xBFDate;Jour;PN1;PE40;Événement / remarque\r\n"
            ."2026-09-18;vendredi;Séance bloc;Séance PMT;REPRISE\r\n"
            ."2026-09-19;samedi;;;\r\n"
            ."2026-09-23;mercredi;Séance bloc;;FOSSE 1\r\n"
            ."2026-09-25;vendredi;Séance bloc;Séance bloc;\r\n");
        $r = (new PlanningCsvImporter($em, $sRepo, $acRepo, $aRepo, $lRepo))->import($f);
        unlink($f);

        $this->assertSame([], $r['errors']);
        $this->assertSame(['14/09/2026', '21/09/2026'], $r['semaines']);
        // 18/09 : bloc + PMT + « Autres » (REPRISE) ; 23/09 : bloc + « Autres » (FOSSE 1) ; 25/09 : bloc
        $this->assertSame(6, $r['creneaux']);
        /** @var PlanningSemaine[] $persistes */
        $c = $persistes[0]->getCreneaux();
        $this->assertCount(3, $c);
        $autres = $c[2];
        $this->assertSame('REPRISE', $autres->getLibelle());
        $this->assertTrue($autres->estAutres());
        $this->assertCount(0, $autres->getLieux());
        $this->assertSame(Jour::Mercredi, $persistes[1]->getCreneaux()[0]->getJour());
        $this->assertSame(['Ligne 1', 'Ligne 2'], array_map(fn ($l) => $l->getNom(), $c[0]->getLieux()->toArray()));
        // La colonne PN1 donne l'aptitude PN1 (et non « tous niveaux »)
        $this->assertSame(['PN1'], array_map(fn ($a) => $a->getCode(), $c[0]->getAptitudes()->toArray()));
        // Un seul créneau le 25/09 : lignes partagées incluses
        $this->assertCount(4, $persistes[1]->getCreneaux()[0]->getLieux());
    }

    public function testSemaineDeVacancesCreeeFermee(): void
    {
        $aRepo = $this->createStub(AptitudeRepository::class);
        $aRepo->method('findOneBy')->willReturn((new Aptitude())->setCode('PN1'));
        $acRepo = $this->createStub(ActiviteRepository::class);
        $acRepo->method('findAll')->willReturn([]);
        $sRepo = $this->createStub(PlanningSemaineRepository::class);
        $sRepo->method('findOneBy')->willReturn(null);
        $persistes = [];
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function ($o) use (&$persistes) { $persistes[] = $o; });

        $f = tempnam(sys_get_temp_dir(), 'plan');
        file_put_contents($f, "Date,Jour,PN1,Événement,Vacances\n"
            ."2026-10-19,lundi,,,x\n"
            ."2026-10-23,vendredi,,,x\n"
            ."2026-11-11,mercredi,,,x\n");
        $r = (new PlanningCsvImporter($em, $sRepo, $acRepo, $aRepo, $this->createStub(LieuActiviteRepository::class)))->import($f);
        unlink($f);

        $this->assertSame([], $r['errors']);
        $this->assertSame(0, $r['creneaux']);
        $semaines = array_values(array_filter($persistes, fn ($o) => $o instanceof PlanningSemaine));
        $this->assertCount(1, $semaines, 'un mercredi férié seul ne ferme pas la semaine');
        $this->assertTrue($semaines[0]->isFermee());
        $this->assertSame('Vacances', $semaines[0]->getMotif());
    }

    public function testCorrigerPn1AjouteLAptitudeAuxCreneauxExistantsSansRecreer(): void
    {
        $apt = fn (string $c) => (new Aptitude())->setCode($c);
        $pn1 = $apt('PN1');
        $aRepo = $this->createStub(AptitudeRepository::class);
        $aRepo->method('findOneBy')->willReturnCallback(fn (array $c) => 'PN1' === $c['code'] ? $pn1 : null);

        $bloc = (new Activite())->setCode('BLOC')->setLibelle('Séance bloc');
        $acRepo = $this->createStub(ActiviteRepository::class);
        $acRepo->method('findAll')->willReturn([$bloc]);

        // Semaine du 14/09 déjà importée, avec le créneau « bloc » du vendredi sans aucune aptitude
        $semaine = (new PlanningSemaine())->setLundi(new \DateTimeImmutable('2026-09-14'));
        $creneau = (new PlanningCreneau())->setJour(Jour::Vendredi)->addActivite($bloc);
        $semaine->addCreneau($creneau);
        $sRepo = $this->createStub(PlanningSemaineRepository::class);
        $sRepo->method('findOneBy')->willReturnCallback(fn (array $c) => $c['lundi']->format('Y-m-d') === '2026-09-14' ? $semaine : null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->exactly(2))->method('flush');

        $csv = "\xEF\xBB\xBFDate;Jour;PN1;PE40;Événement / remarque\r\n"
            ."2026-09-18;vendredi;Séance bloc;Séance PMT;REPRISE\r\n"
            ."2026-09-25;vendredi;Séance bloc;;\r\n";
        $importer = new PlanningCsvImporter($em, $sRepo, $acRepo, $aRepo, $this->createStub(LieuActiviteRepository::class));

        $r = $importer->corrigerPn1($csv);
        $this->assertSame(1, $r['ajoutes']);
        $this->assertSame(0, $r['deja_presents']);
        $this->assertSame(['21/09/2026'], $r['semaines_absentes']); // le 25/09 est dans la semaine du 21/09
        $this->assertSame(['PN1'], array_map(fn ($a) => $a->getCode(), $creneau->getAptitudes()->toArray()));
        $this->assertCount(1, $semaine->getCreneaux(), 'aucun créneau créé ou supprimé');

        // Relancer ne change rien
        $r2 = $importer->corrigerPn1($csv);
        $this->assertSame(0, $r2['ajoutes']);
        $this->assertSame(1, $r2['deja_presents']);
    }
}
