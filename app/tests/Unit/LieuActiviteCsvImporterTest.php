<?php

namespace App\Tests\Unit;

use App\Entity\LieuActivite;
use App\Lieu\LieuActiviteCsvImporter;
use App\Repository\LieuActiviteRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class LieuActiviteCsvImporterTest extends TestCase
{
    public function testCreeMetAJourEtSignaleLesErreurs(): void
    {
        $existant = (new LieuActivite())->setGroupe('Salles')->setNom('Bureau')->setOrdre(5);
        $repo = $this->createStub(LieuActiviteRepository::class);
        $repo->method('findAll')->willReturn([$existant]);
        $persistes = [];
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function ($o) use (&$persistes) { $persistes[] = $o; });
        $em->expects($this->once())->method('flush');

        $f = tempnam(sys_get_temp_dir(), 'lieux');
        file_put_contents($f, "\xEF\xBB\xBFgroupe;nom;ordre\nPetit bassin;Ligne 1;11\nsalles;bureau;101\nPetit bassin;Ligne 1;12\n;Ligne 2;3\nGrand bassin;Ligne 1;abc\n");
        $r = (new LieuActiviteCsvImporter($em, $repo))->import($f);
        unlink($f);

        $this->assertSame(1, $r['created']);
        $this->assertSame(1, $r['updated']);
        $this->assertCount(3, $r['errors']);
        $this->assertSame(101, $existant->getOrdre());
        $this->assertCount(2, $persistes);
    }
}
