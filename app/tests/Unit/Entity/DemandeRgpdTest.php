<?php

namespace App\Tests\Unit\Entity;

use App\Entity\DemandeRgpd;
use App\Entity\User;
use App\Enum\TypeDemandeRgpd;
use PHPUnit\Framework\TestCase;

class DemandeRgpdTest extends TestCase
{
    public function testLeJetonClairNEstQueDansLInstanceCreeEtSeulSonHashEstStocke(): void
    {
        $demande = new DemandeRgpd(new User(), TypeDemandeRgpd::Information, '2026-10');

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', (string) $demande->getJeton());
        $this->assertSame(hash('sha256', (string) $demande->getJeton()), $demande->getTokenHash());
        $this->assertNotSame($demande->getJeton(), $demande->getTokenHash());
    }

    public function testDeuxDemandesOntDesJetonsDifferents(): void
    {
        $user = new User();

        $this->assertNotSame(
            (new DemandeRgpd($user, TypeDemandeRgpd::Communication, '2026-10'))->getJeton(),
            (new DemandeRgpd($user, TypeDemandeRgpd::Communication, '2026-10'))->getJeton(),
        );
    }

    public function testUnAccuseDeLectureNEnregistrePasDAccord(): void
    {
        $demande = new DemandeRgpd(new User(), TypeDemandeRgpd::Information, '2026-10');

        $this->assertFalse($demande->estRepondue());

        $demande->repondre(null);

        $this->assertTrue($demande->estRepondue());
        $this->assertNull($demande->getAccord());
    }

    public function testLeChoixSurLesCommunicationsEstEnregistreEtPeutEtreModifie(): void
    {
        $demande = new DemandeRgpd(new User(), TypeDemandeRgpd::Communication, '2026-10');

        $demande->repondre(true);
        $this->assertTrue($demande->getAccord());

        $demande->repondre(false);
        $this->assertFalse($demande->getAccord());
        $this->assertNotNull($demande->getRepondueLe());
    }
}
