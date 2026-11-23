<?php

namespace App\Tests\Unit\Entity;

use App\Entity\FicheSecurite;
use App\Entity\InvitationFosse;
use App\Entity\Palanquee;
use PHPUnit\Framework\TestCase;

class PalanqueeParametresTest extends TestCase
{
    public function testParametresEnregistres(): void
    {
        $palanquee = (new FicheSecurite(new InvitationFosse()))->ajouterPalanquee();
        $palanquee->setParametres(30, 12, 45, 'encadree');

        $this->assertSame([30, 12, 45, 'encadree'], [$palanquee->getProfondeurMax(), $palanquee->getDtr(), $palanquee->getDuree(), $palanquee->getType()]);
    }

    public function testTypeInconnuRefuse(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new FicheSecurite(new InvitationFosse()))->ajouterPalanquee()->setParametres(null, null, null, 'inconnu');
    }
}
