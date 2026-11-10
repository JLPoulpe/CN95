<?php

namespace App\Tests\Unit;

use App\Entity\Activite;
use App\Entity\Aptitude;
use App\Entity\LieuActivite;
use App\Entity\PlanningSemaine;
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
        $this->assertSame(3, $r['creneaux']);
        $this->assertCount(1, array_filter($r['ignored'], fn ($m) => str_contains($m, '23/09/2026')));
        /** @var PlanningSemaine[] $persistes */
        $c = $persistes[0]->getCreneaux();
        $this->assertCount(4 - 2, $c); // 18/09 : bloc + PMT
        $this->assertSame(['Ligne 1', 'Ligne 2'], array_map(fn ($l) => $l->getNom(), $c[0]->getLieux()->toArray()));
        // Un seul créneau le 25/09 : lignes partagées incluses
        $this->assertCount(4, $persistes[1]->getCreneaux()[0]->getLieux());
    }
}
