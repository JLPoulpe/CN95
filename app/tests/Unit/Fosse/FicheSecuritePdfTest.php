<?php

namespace App\Tests\Unit\Fosse;

use App\Entity\FicheSecurite;
use App\Entity\InvitationFosse;
use App\Fosse\FicheSecuritePdf;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class FicheSecuritePdfTest extends TestCase
{
    private function service(): FicheSecuritePdf
    {
        return new FicheSecuritePdf(new Environment(new FilesystemLoader(dirname(__DIR__, 3).'/templates')));
    }

    public function testBlocsCompletesJusquaNeufParFeuille(): void
    {
        $fiche = new FicheSecurite((new InvitationFosse())->setDate(new \DateTimeImmutable('2026-12-05')));
        $fiche->ajouterPalanquee()->ajouterExterieur(1);

        $donnees = $this->service()->donnees($fiche);

        self::assertCount(1, $donnees['feuilles']);
        self::assertCount(3, $donnees['feuilles'][0]);
        self::assertSame('Moniteur extérieur 1', $donnees['feuilles'][0][0][0]['encadrant']);
        self::assertSame('', $donnees['feuilles'][0][2][2]['encadrant']);
        self::assertSame(1, $donnees['effectif']);
    }

    public function testDixPalanqueesDonnentDeuxFeuilles(): void
    {
        $fiche = new FicheSecurite((new InvitationFosse())->setDate(new \DateTimeImmutable('2026-12-05')));
        for ($i = 0; $i < 10; ++$i) {
            $fiche->ajouterPalanquee();
        }

        self::assertCount(2, $this->service()->donnees($fiche)['feuilles']);
    }

    public function testGenerationDunPdf(): void
    {
        $fiche = new FicheSecurite((new InvitationFosse())->setDate(new \DateTimeImmutable('2026-12-05')));

        self::assertStringStartsWith('%PDF', $this->service()->generer($fiche));
    }
}
